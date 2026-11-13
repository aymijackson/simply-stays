<?php

namespace Modules\Operations\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\InventoryItem;
use Modules\Operations\Models\InventoryLocation;
use Modules\Operations\Models\InventoryStock;
use Modules\Operations\Services\StockLedger;

class InventoryLocationController extends OpsController
{
    public function index(Request $request): View
    {
        $property = $this->property($request, 'inventory');

        $locations = InventoryLocation::query()
            ->where('property_id', $property->id)
            ->with('listing')
            ->withCount(['stocks as lines_count' => fn ($q) => $q->where('quantity', '>', 0)])
            ->withSum('stocks as units_sum', 'quantity')
            ->withCount(['stocks as short_count' => fn ($q) => $q->whereNotNull('par_level')->whereColumn('quantity', '<', 'par_level')])
            ->orderBy('sort_order')->orderBy('name')->orderBy('area')
            ->get();

        return view('operations::inventory.locations.index', [
            'property' => $property,
            'groups' => $locations->groupBy(fn ($l) => InventoryLocation::TYPES[$l->type] ?? 'Other'),
            'canManage' => $this->can($request, $property, 'manager'),
        ]);
    }

    public function create(Request $request): View
    {
        $property = $this->property($request, 'manager');

        return view('operations::inventory.locations.form', [
            'property' => $property,
            'location' => new InventoryLocation(['type' => $request->input('type', 'apartment')]),
            'listings' => $this->listings($property),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $property = $this->property($request, 'manager');
        $location = InventoryLocation::create($this->validated($request, $property) + ['property_id' => $property->id]);

        return redirect()->route('ops.inventory.locations.show', $location)->with('status', 'Location added.');
    }

    public function show(Request $request, InventoryLocation $location): View
    {
        $property = $this->propertyOf($request, $location, 'inventory');

        $stocks = $location->stocks()->with('item')->get()
            ->sortBy(fn (InventoryStock $s) => [array_search($s->item->kind, array_keys(InventoryItem::KINDS)), $s->item->name])
            ->values();

        return view('operations::inventory.locations.show', [
            'property' => $property,
            'location' => $location->load('listing'),
            'stocks' => $stocks,
            'stores' => InventoryLocation::where('property_id', $property->id)->where('type', 'store')->where('id', '!=', $location->id)->orderBy('name')->get(),
            'otherItems' => InventoryItem::where('property_id', $property->id)->where('is_active', true)->whereNotIn('id', $stocks->pluck('item_id'))->orderBy('name')->get(['id', 'name', 'unit']),
            'canManage' => $this->can($request, $property, 'manager'),
        ]);
    }

    public function edit(Request $request, InventoryLocation $location): View
    {
        $property = $this->propertyOf($request, $location, 'manager');

        return view('operations::inventory.locations.form', [
            'property' => $property,
            'location' => $location,
            'listings' => $this->listings($property),
        ]);
    }

    public function update(Request $request, InventoryLocation $location): RedirectResponse
    {
        $property = $this->propertyOf($request, $location, 'manager');
        $location->update($this->validated($request, $property));

        return redirect()->route('ops.inventory.locations.show', $location)->with('status', 'Location updated.');
    }

    public function destroy(Request $request, InventoryLocation $location): RedirectResponse
    {
        $this->propertyOf($request, $location, 'manager');

        if ($location->stocks()->where('quantity', '!=', 0)->exists()) {
            return back()->withErrors(['location' => 'Move or write off the stock in this location before deleting it.']);
        }

        $location->delete();

        return redirect()->route('ops.inventory.locations.index')->with('status', 'Location deleted.');
    }

    /**
     * Stock-take: save counted quantities (as adjustments) and par levels in one go.
     * Also used to start tracking a new item in this location.
     */
    public function count(Request $request, InventoryLocation $location): RedirectResponse
    {
        $property = $this->propertyOf($request, $location, 'inventory');
        $canManage = $this->can($request, $property, 'manager');

        $data = $request->validate([
            'counted' => ['array'],
            'counted.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'par' => ['array'],
            'par.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'new_item_id' => ['nullable', Rule::exists('ops_inventory_items', 'id')->where('property_id', $property->id)],
            'new_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'new_par' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $attributes = ['recorded_by' => $request->user()->id, 'note' => 'Stock count'];
        $adjusted = 0;

        DB::transaction(function () use ($data, $location, $canManage, $attributes, &$adjusted) {
            $stocks = $location->stocks()->with('item')->get()->keyBy('id');

            foreach ($data['counted'] ?? [] as $stockId => $counted) {
                if ($counted === null || ! $stocks->has($stockId)) {
                    continue;
                }
                if (StockLedger::count($stocks[$stockId]->item, $location, (int) $counted, $attributes)) {
                    $adjusted++;
                }
            }

            if ($canManage) {
                foreach ($data['par'] ?? [] as $stockId => $par) {
                    if ($stocks->has($stockId)) {
                        InventoryStock::whereKey($stockId)->update(['par_level' => $par === null ? null : (int) $par]);
                    }
                }
            }

            if (! empty($data['new_item_id'])) {
                $item = InventoryItem::find($data['new_item_id']);
                if (! empty($data['new_quantity'])) {
                    StockLedger::count($item, $location, (int) $data['new_quantity'], $attributes);
                    $adjusted++;
                }
                InventoryStock::firstOrCreate(['item_id' => $item->id, 'location_id' => $location->id], ['quantity' => 0])
                    ->update($canManage && isset($data['new_par']) ? ['par_level' => (int) $data['new_par']] : []);
            }
        });

        return redirect()->route('ops.inventory.locations.show', $location)
            ->with('status', $adjusted ? "Stock count saved — {$adjusted} line(s) adjusted." : 'Stock count saved — everything matched.');
    }

    /** Top every short line back up to par, taking stock from the chosen store. */
    public function restock(Request $request, InventoryLocation $location): RedirectResponse
    {
        $property = $this->propertyOf($request, $location, 'inventory');
        $data = $request->validate([
            'store_id' => ['required', Rule::exists('ops_inventory_locations', 'id')->where('property_id', $property->id)],
        ]);
        $store = InventoryLocation::findOrFail($data['store_id']);

        $moved = [];
        $missing = [];

        foreach ($location->stocks()->with('item')->get() as $stock) {
            $short = $stock->shortfall();
            if ($short === 0) {
                continue;
            }

            $available = StockLedger::quantity($stock->item, $store);
            $take = min($short, $available);

            if ($take > 0) {
                StockLedger::record($stock->item, 'transfer', $take, $store, $location, [
                    'recorded_by' => $request->user()->id,
                    'note' => 'Restocked to par',
                ]);
                $moved[] = "{$take} {$stock->item->name}";
            }
            if ($take < $short) {
                $missing[] = ($short - $take).' '.$stock->item->name;
            }
        }

        $message = $moved ? 'Restocked: '.implode(', ', $moved).'.' : 'Nothing needed restocking.';
        if ($missing) {
            $message .= ' Not enough in '.$store->label().' for: '.implode(', ', $missing).'.';
        }

        return redirect()->route('ops.inventory.locations.show', $location)->with('status', $message);
    }

    private function validated(Request $request, Property $property): array
    {
        $data = $request->validate($this->rules($property));
        $data['sort_order'] ??= 0;

        return $data;
    }

    private function rules(Property $property): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:60'],
            'type' => ['required', Rule::in(array_keys(InventoryLocation::TYPES))],
            'listing_id' => ['nullable', Rule::exists('listings', 'id')->where('property_id', $property->id)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function listings(Property $property)
    {
        return Listing::where('property_id', $property->id)->orderBy('title')->get(['id', 'title']);
    }
}
