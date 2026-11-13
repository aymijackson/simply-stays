<?php

namespace Modules\Services\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Services\Models\MenuItem;
use Modules\Services\Models\Service;

/**
 * Laundry price list: garments (rows) x treatments (columns), e.g.
 *
 *             Wash only   Wash & iron   Wash, iron & starch
 *   Shirt        800          1,200            1,500
 *   Jeans      1,000          1,500              —
 *
 * Stored on the existing menu tables so ordering, receipts and order history
 * work unchanged: each garment is a MenuItem priced 0 with one required
 * "Treatment" choice whose options carry the price for that treatment.
 * A blank cell means that treatment isn't offered for the garment.
 */
class PriceList
{
    public const GROUP = 'Treatment';

    public const DEFAULT_TREATMENTS = ['Wash only', 'Wash & iron', 'Wash, iron & starch', 'Iron only', 'Dry clean'];

    public const DEFAULT_GARMENTS = [
        'Clothing' => ['Shirt', 'T-shirt', 'Trousers', 'Jeans', 'Shorts', 'Dress', 'Skirt', 'Native attire (2-piece)', 'Suit (2-piece)', 'Jacket'],
        'Household' => ['Bedsheet', 'Duvet cover', 'Pillow case', 'Towel', 'Curtain (per panel)'],
    ];

    /**
     * @return array{treatments: list<string>, garments: Collection<int, array{item: MenuItem, prices: array<string, array{option_id: int, amount: int, available: bool}>}>}|null
     */
    public static function for(Service $service): ?array
    {
        if (! $service->isLaundry()) {
            return null;
        }

        $items = $service->menuItems()->with('optionGroups.options')->orderBy('category')->orderBy('id')->get();
        $treatments = [];
        $garments = collect();

        foreach ($items as $item) {
            $prices = [];
            foreach ($item->optionGroups->flatMap->options->sortBy('id') as $option) {
                $treatments[$option->name] ??= count($treatments);
                $prices[$option->name] = [
                    'option_id' => $option->id,
                    'amount' => $item->price_amount + $option->price_delta_amount,
                    'available' => (bool) $option->is_available,
                ];
            }
            $garments->push(['item' => $item, 'prices' => $prices]);
        }

        return ['treatments' => array_keys($treatments), 'garments' => $garments];
    }

    /**
     * @param  list<string>  $treatments  column names in display order
     * @param  list<array{id?: int|string|null, name: string, category?: ?string, available?: bool, prices: array<int, int|null>}>  $rows
     *                                                                                                                                     prices keyed by column index, in minor units (null = not offered)
     */
    public static function save(Service $service, array $treatments, array $rows): void
    {
        DB::transaction(function () use ($service, $treatments, $rows) {
            $existing = $service->menuItems()->with('optionGroups.options')->get()->keyBy('id');
            $kept = [];

            foreach ($rows as $row) {
                $item = isset($row['id']) && $existing->has((int) $row['id'])
                    ? $existing->get((int) $row['id'])
                    : new MenuItem(['service_id' => $service->id]);

                $item->fill([
                    'name' => $row['name'],
                    'category' => $row['category'] ?? null,
                    'price_amount' => 0,
                    'currency' => $service->currency,
                    'is_available' => $row['available'] ?? true,
                ])->save();
                $kept[] = $item->id;

                $group = $item->optionGroups()->firstOrCreate(['name' => self::GROUP], [
                    'selection_type' => 'single',
                    'is_required' => true,
                    'min_select' => 1,
                    'max_select' => 1,
                ]);
                $options = $group->options()->get()->keyBy('name');

                foreach ($treatments as $index => $treatment) {
                    $amount = $row['prices'][$index] ?? null;
                    $option = $options->get($treatment);

                    if ($amount === null) {
                        // Not offered for this garment. Keep the option (old orders point at it) but hide it.
                        $option?->update(['is_available' => false]);

                        continue;
                    }

                    if ($option) {
                        $option->update(['price_delta_amount' => $amount, 'is_available' => true]);
                    } else {
                        $group->options()->create(['name' => $treatment, 'price_delta_amount' => $amount, 'is_available' => true]);
                    }
                }

                // Columns removed from the grid are no longer offered.
                $options->keys()->diff($treatments)->each(fn ($name) => $options[$name]->update(['is_available' => false]));
            }

            // Rows removed from the grid. Past order lines keep their own copy of name and price.
            $existing->keys()->diff($kept)->each(fn ($id) => $existing[$id]->delete());
        });
    }

    /** Parse "1,500" / "1500.50" into minor units; blank = not offered. */
    public static function toMinor(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-' || $value === '—') {
            return null;
        }

        return (int) round(((float) str_replace(',', '', $value)) * 100);
    }
}
