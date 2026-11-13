<?php

namespace Modules\Services\Support;

use Illuminate\Support\Facades\DB;
use Modules\Core\Support\PricingCalculator;
use Modules\Listings\Models\Property;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceOrder;

/**
 * Builds a service order and its bill. Shared by signed-in checkout and QR
 * ordering so both price the same way:
 *
 *   subtotal  = flat service price, or the sum of price-list lines
 *   + charges = service charge, VAT/tax and any other charge (the service's
 *               own rates, falling back to the property's)
 *   + delivery fee for price-list services (food, laundry)
 */
class OrderBuilder
{
    /**
     * @param  array<string, mixed>  $attributes  order columns (customer, booking, contact, location, details, ...)
     * @param  array<string, mixed>  $input  raw request input: items[...] for food, laundry[...] for laundry
     */
    public static function place(Service $service, array $attributes, array $input, ?Property $property): ServiceOrder
    {
        return DB::transaction(function () use ($service, $attributes, $input, $property) {
            $priceList = $service->usesPriceList();

            [$lines, $subtotal] = match (true) {
                $priceList && $service->isLaundry() => self::laundryLines($service, $input['laundry'] ?? []),
                $priceList => self::foodLines($service, $input['items'] ?? []),
                default => [[], (int) $service->price_amount],
            };

            $rates = $service->chargeRates($property);
            $breakdown = PricingCalculator::breakdown($subtotal, $rates['service_charge'], $rates['tax'], $rates['other'], $rates['other_label']);

            if ($priceList) {
                $breakdown['delivery_fee'] = DeliveryFeeCalculator::feeFor($service, $subtotal);
                $breakdown['total'] += $breakdown['delivery_fee'];
            }

            // Flat-priced orders with no charges keep their old shape: a plain total, no breakdown.
            $hasCharges = $breakdown['total'] !== $subtotal || $priceList;

            $order = ServiceOrder::create($attributes + [
                'service_id' => $service->id,
                'status' => 'pending',
                'total_amount' => $breakdown['total'],
                'currency' => $service->currency,
                'price_breakdown' => $hasCharges ? $breakdown : null,
            ]);

            foreach ($lines as $line) {
                $optionRows = $line['options'] ?? [];
                unset($line['options']);

                $item = $order->items()->create($line);

                foreach ($optionRows as $optionRow) {
                    $item->options()->create($optionRow);
                }
            }

            return $order;
        });
    }

    /**
     * Laundry: laundry[garmentId][treatmentOptionId] = quantity.
     * Each garment + treatment pair becomes its own line, e.g. "3 x Shirt — Wash & iron".
     *
     * @return array{0: array<int, array>, 1: int}
     */
    public static function laundryLines(Service $service, array $input): array
    {
        $garments = $service->menuItems()->where('is_available', true)->with('optionGroups.options')->get()->keyBy('id');
        $lines = [];
        $subtotal = 0;

        foreach ($input as $garmentId => $treatments) {
            $garment = $garments->get((int) $garmentId);
            abort_unless($garment && is_array($treatments), 422, 'That item is no longer on the price list.');

            $options = $garment->optionGroups->flatMap->options->keyBy('id');

            foreach ($treatments as $optionId => $quantity) {
                $quantity = (int) $quantity;
                if ($quantity < 1) {
                    continue;
                }
                abort_if($quantity > 500, 422, 'That quantity is too large.');

                $option = $options->get((int) $optionId);
                abort_unless($option && $option->is_available, 422, "That option isn't available for {$garment->name}.");

                $unit = $garment->price_amount + $option->price_delta_amount;
                $lines[] = [
                    'menu_item_id' => $garment->id,
                    'name' => "{$garment->name} — {$option->name}",
                    'unit_price_amount' => $unit,
                    'quantity' => $quantity,
                    'line_total_amount' => $unit * $quantity,
                ];
                $subtotal += $unit * $quantity;
            }
        }

        abort_if(empty($lines), 422, 'Add at least one item.');

        return [$lines, $subtotal];
    }

    /**
     * Food menu: items[menuItemId][quantity] with nested options[groupId] (see the order form).
     *
     * @return array{0: array<int, array>, 1: int}
     */
    public static function foodLines(Service $service, array $items): array
    {
        $subtotal = 0;
        $lines = [];

        foreach ($items as $menuItemId => $itemInput) {
            $quantity = (int) ($itemInput['quantity'] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            $menuItem = $service->menuItems()->where('is_available', true)->with('optionGroups.options')->findOrFail($menuItemId);

            $optionLines = [];

            foreach ($menuItem->optionGroups as $group) {
                $groupInput = $itemInput['options'][$group->id] ?? null;

                if ($group->isSingle()) {
                    abort_if($group->is_required && ! $groupInput, 422, "Please choose an option for \"{$group->name}\".");

                    if (! $groupInput) {
                        continue;
                    }

                    $option = $group->options->firstWhere('id', (int) $groupInput);
                    abort_unless($option && $option->is_available, 422, "Invalid selection for \"{$group->name}\".");

                    $optionLines[] = [
                        'menu_item_option_id' => $option->id,
                        'name' => $option->name,
                        'price_delta_amount' => $option->price_delta_amount,
                        'quantity' => 1,
                        'line_total_amount' => $option->price_delta_amount * $quantity,
                    ];

                    continue;
                }

                $selections = is_array($groupInput) ? $groupInput : [];
                $totalSelected = 0;

                foreach ($selections as $optionId => $optQty) {
                    $optQty = (int) $optQty;

                    if ($optQty < 1) {
                        continue;
                    }

                    $option = $group->options->firstWhere('id', (int) $optionId);
                    abort_unless($option && $option->is_available, 422, "Invalid selection for \"{$group->name}\".");
                    abort_if($optQty > $option->max_quantity, 422, "\"{$option->name}\" is limited to {$option->max_quantity} per order.");

                    $totalSelected += $optQty;

                    $optionLines[] = [
                        'menu_item_option_id' => $option->id,
                        'name' => $option->name,
                        'price_delta_amount' => $option->price_delta_amount,
                        'quantity' => $optQty,
                        'line_total_amount' => $option->price_delta_amount * $optQty * $quantity,
                    ];
                }

                abort_if($group->max_select && $totalSelected > $group->max_select, 422, "\"{$group->name}\" allows at most {$group->max_select}.");
                abort_if($group->is_required && $totalSelected < max($group->min_select, 1), 422, "Please choose at least one option for \"{$group->name}\".");
            }

            $baseLineTotal = $menuItem->price_amount * $quantity;
            $optionsLineTotal = array_sum(array_column($optionLines, 'line_total_amount'));
            $itemSubtotal = $baseLineTotal + $optionsLineTotal;
            $subtotal += $itemSubtotal;

            $lines[] = [
                'menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'unit_price_amount' => $menuItem->price_amount,
                'quantity' => $quantity,
                'line_total_amount' => $itemSubtotal,
                'options' => $optionLines,
            ];
        }

        abort_if(empty($lines), 422, 'Select at least one menu item.');

        return [$lines, $subtotal];
    }
}
