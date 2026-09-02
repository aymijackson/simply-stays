<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Bookings\Models\Booking;
use Modules\Core\Support\PricingCalculator;
use Modules\Reviews\Models\Review;
use Modules\Services\Fulfillment\ServiceFulfillmentManager;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceOrder;
use Modules\Services\Models\ServiceOrderItem;
use Modules\Services\Support\DeliveryFeeCalculator;

class ServiceOrderController extends Controller
{
    public function create(Service $service, ServiceFulfillmentManager $handlers): View
    {
        abort_unless($service->status === 'published', 404);

        $handler = $handlers->driver($service->category->fulfillment_type);
        $isFood = $service->category->fulfillment_type === 'food';
        $menuItems = $isFood
            ? $service->menuItems()
                ->where('is_available', true)
                ->with(['optionGroups.options', 'upsells', 'pairings'])
                ->get()
            : collect();

        $orderedBeforeIds = $isFood
            ? ServiceOrderItem::whereHas('order', fn ($q) => $q->where('service_id', $service->id)->where('customer_id', Auth::id()))
                ->whereNotNull('menu_item_id')
                ->distinct()
                ->pluck('menu_item_id')
            : collect();

        $orderedBefore = $menuItems->whereIn('id', $orderedBeforeIds);

        $requiresBooking = $service->requiresBooking();
        $allowsBookingLink = $service->allowsBookingLink();

        $myBookings = $allowsBookingLink
            ? Booking::with('listing.property')
                ->where('guest_id', Auth::id())
                ->whereIn('status', ['pending', 'confirmed'])
                ->get()
                ->filter(fn (Booking $booking) => $service->isAvailableForProperty($booking->listing->property_id))
            : collect();

        return view('services::order', compact('service', 'handler', 'myBookings', 'isFood', 'menuItems', 'orderedBefore', 'requiresBooking', 'allowsBookingLink'));
    }

    public function store(Request $request, Service $service, ServiceFulfillmentManager $handlers): RedirectResponse
    {
        abort_unless($service->status === 'published', 404);

        $handler = $handlers->driver($service->category->fulfillment_type);
        $isFood = $service->category->fulfillment_type === 'food';
        $requiresBooking = $service->requiresBooking();
        $allowsBookingLink = $service->allowsBookingLink();

        $rules = [
            'booking_id' => $requiresBooking ? ['required', 'exists:bookings,id'] : ['nullable', 'exists:bookings,id'],
        ];

        foreach ($handler->validationRules() as $field => $fieldRules) {
            // When a booking is required, its address stands in for a manually
            // typed delivery address, so don't demand one from the guest.
            if ($requiresBooking && $field === 'delivery_address') {
                continue;
            }

            $rules["details.{$field}"] = $fieldRules;
        }

        if ($isFood) {
            $rules['items'] = ['required', 'array'];
            $rules['items.*.quantity'] = ['nullable', 'integer', 'min:0'];
        }

        $validated = $request->validate($rules);

        $booking = null;
        if ($allowsBookingLink && ! empty($validated['booking_id'])) {
            $booking = Booking::with('listing.property')->findOrFail($validated['booking_id']);
            abort_unless($booking->guest_id === Auth::id(), 403);
            abort_unless($service->isAvailableForProperty($booking->listing->property_id), 403, 'This service is not available at your booking\'s property.');
        }

        $details = $validated['details'] ?? [];

        if ($requiresBooking) {
            $details['delivery_address'] = $booking->deliveryAddressLabel();
        }
        // Options/quantities are nested arbitrarily deep per option group and are validated
        // manually in buildFoodOrderLines(), so pull them from raw input rather than
        // $validated — Validator::validated() only returns keys covered by declared rules
        // and would silently drop the "options" sub-array otherwise.
        $rawItems = $request->input('items', []);

        $order = DB::transaction(function () use ($service, $booking, $details, $isFood, $rawItems) {
            if ($isFood) {
                [$lines, $subtotal] = $this->buildFoodOrderLines($service, $rawItems);

                $deliveryFee = DeliveryFeeCalculator::feeFor($service, $subtotal);

                $property = $booking?->listing?->property;
                $breakdown = PricingCalculator::breakdown(
                    $subtotal,
                    $property ? (float) $property->service_charge_percent : 0.0,
                    $property ? (float) $property->tax_percent : 0.0,
                );
                $breakdown['delivery_fee'] = $deliveryFee;
                $breakdown['total'] += $deliveryFee;

                $order = ServiceOrder::create([
                    'service_id' => $service->id,
                    'customer_id' => Auth::id(),
                    'booking_id' => $booking?->id,
                    'status' => 'pending',
                    'fulfillment_details' => $details,
                    'scheduled_at' => $details['requested_time'] ?? null,
                    'total_amount' => $breakdown['total'],
                    'currency' => $service->currency,
                    'price_breakdown' => $breakdown,
                ]);

                foreach ($lines as $line) {
                    $optionRows = $line['options'];
                    unset($line['options']);

                    $orderItem = $order->items()->create($line);

                    foreach ($optionRows as $optionRow) {
                        $orderItem->options()->create($optionRow);
                    }
                }

                return $order;
            }

            return ServiceOrder::create([
                'service_id' => $service->id,
                'customer_id' => Auth::id(),
                'booking_id' => $booking?->id,
                'status' => 'pending',
                'fulfillment_details' => $details,
                'scheduled_at' => $details['scheduled_time'] ?? null,
                'total_amount' => $service->price_amount,
                'currency' => $service->currency,
            ]);
        });

        return redirect()->route('service-orders.show', $order)
            ->with('status', 'Order placed! The provider will confirm shortly. You can pay now or after confirmation.');
    }

    /**
     * @return array{0: array<int, array>, 1: int}
     */
    private function buildFoodOrderLines(Service $service, array $items): array
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

    public function index(): View
    {
        $orders = ServiceOrder::with('service.category')
            ->where('customer_id', Auth::id())
            ->latest()
            ->get();

        return view('services::orders', compact('orders'));
    }

    public function show(ServiceOrder $order, ServiceFulfillmentManager $handlers): View
    {
        $this->authorizeParticipant($order);

        $order->load(['service.provider', 'booking', 'items.options']);
        $handler = $handlers->driver($order->service->category->fulfillment_type);

        $user = Auth::user();
        $canReview = $order->customer_id === $user->id
            && $order->status === 'completed'
            && ! Review::where('reviewer_id', $user->id)
                ->where('reviewable_type', $order->service::class)
                ->where('reviewable_id', $order->service_id)
                ->exists();

        return view('services::order-show', compact('order', 'handler', 'canReview'));
    }

    public function cancel(ServiceOrder $order): RedirectResponse
    {
        $this->authorizeParticipant($order);

        abort_unless(in_array($order->status, ['pending', 'confirmed']), 422);

        $order->update(['status' => 'cancelled']);

        return back()->with('status', 'Order cancelled.');
    }

    private function authorizeParticipant(ServiceOrder $order): void
    {
        $user = Auth::user();

        abort_unless(
            $order->customer_id === $user->id || $order->service->provider_id === $user->id || $user->hasRole('Admin'),
            403
        );
    }
}
