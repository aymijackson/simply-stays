<?php

namespace Modules\Operations\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Support\RouteKey;
use Modules\Operations\Models\InventoryItem;
use Modules\Operations\Models\InventoryLocation;
use Modules\Operations\Support\StockAlerts;

/** Stock overview: every item with its quantity in the store and across the property. */
class InventoryController extends OpsController
{
    public function index(Request $request): View
    {
        $property = $this->property($request, 'inventory');
        $location = $request->filled('location')
            ? RouteKey::find(InventoryLocation::class, $request->query('location'), InventoryLocation::where('property_id', $property->id))
            : null;

        $query = InventoryItem::query()
            ->where('property_id', $property->id)
            ->when(! $request->boolean('inactive'), fn (Builder $q) => $q->where('is_active', true))
            ->withSum('stocks as total_quantity', 'quantity')
            ->withSum(['stocks as store_quantity' => fn (Builder $q) => $q->whereHas('location', fn (Builder $l) => $l->where('type', 'store'))], 'quantity')
            ->orderBy('name');

        if ($location) {
            $query->whereHas('stocks', fn (Builder $q) => $q->where('location_id', $location->id))
                ->withSum(['stocks as location_quantity' => fn (Builder $q) => $q->where('location_id', $location->id)], 'quantity');
        }
        if ($request->filled('kind')) {
            $query->where('kind', $request->string('kind'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }
        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->string('q').'%');
        }

        if ($request->input('alert') === 'low') {
            $lowIds = StockAlerts::lowStock($property)->pluck('id');
            $query->whereIn('id', $lowIds);
        }

        $items = $query->paginate(50)->withQueryString();

        return view('operations::inventory.index', [
            'property' => $property,
            'items' => $items,
            'location' => $location,
            'locations' => InventoryLocation::where('property_id', $property->id)->orderBy('sort_order')->orderBy('name')->orderBy('area')->get(),
            'categories' => InventoryItem::where('property_id', $property->id)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'kindCounts' => InventoryItem::where('property_id', $property->id)->where('is_active', true)->groupBy('kind')->selectRaw('kind, COUNT(*) as n')->pluck('n', 'kind'),
            'lowStockCount' => StockAlerts::lowStock($property)->count(),
            'canManage' => $this->can($request, $property, 'manager'),
        ]);
    }
}
