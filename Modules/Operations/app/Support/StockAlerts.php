<?php

namespace Modules\Operations\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\InventoryItem;
use Modules\Operations\Models\InventoryStock;

class StockAlerts
{
    /** Items whose quantity left in the store(s) is at or below their reorder level. */
    public static function lowStock(Property $property): Collection
    {
        return InventoryItem::query()
            ->where('property_id', $property->id)
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->withSum(['stocks as store_quantity' => fn (Builder $q) => $q->whereHas('location', fn (Builder $l) => $l->where('type', 'store'))], 'quantity')
            ->orderBy('name')
            ->get()
            ->filter(fn (InventoryItem $item) => (int) $item->store_quantity <= $item->reorder_level)
            ->values();
    }

    /** Stock lines in apartments/rooms that are short of their par level. */
    public static function belowPar(Property $property): Collection
    {
        return InventoryStock::query()
            ->with(['item', 'location'])
            ->whereHas('item', fn (Builder $q) => $q->where('property_id', $property->id)->where('is_active', true))
            ->whereNotNull('par_level')
            ->whereColumn('quantity', '<', 'par_level')
            ->get();
    }
}
