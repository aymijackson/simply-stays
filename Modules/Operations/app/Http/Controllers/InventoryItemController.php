<?php

namespace Modules\Operations\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\InventoryItem;
use Modules\Operations\Models\InventoryLocation;
use Modules\Operations\Services\StockLedger;
use Modules\Operations\Support\OpsContext;

class InventoryItemController extends OpsController
{
    public function create(Request $request): View
    {
        $property = $this->property($request, 'manager');

        return view('operations::inventory.items.create', [
            'property' => $property,
            'item' => new InventoryItem(['kind' => $request->input('kind', 'asset'), 'unit' => 'pcs']),
            'locations' => $this->locations($property),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $property = $this->property($request, 'manager');
        $data = $request->validate($this->rules($property) + [
            'opening_location_id' => ['nullable', Rule::exists('ops_inventory_locations', 'id')->where('property_id', $property->id)],
            'opening_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $item = InventoryItem::create($this->attributes($data) + [
            'property_id' => $property->id,
            'currency' => OpsContext::currency(),
        ]);

        if (! empty($data['opening_location_id']) && ! empty($data['opening_quantity'])) {
            StockLedger::record($item, 'adjust', (int) $data['opening_quantity'], null, InventoryLocation::find($data['opening_location_id']), [
                'recorded_by' => $request->user()->id,
                'note' => 'Opening stock',
            ]);
        }

        return redirect()->route('ops.inventory.items.show', $item)->with('status', "{$item->name} added to the inventory.");
    }

    public function show(Request $request, InventoryItem $item): View
    {
        $property = $this->propertyOf($request, $item, 'inventory');

        return view('operations::inventory.items.show', [
            'property' => $property,
            'item' => $item,
            'stocks' => $item->stocks()->with('location')->get()->sortBy(fn ($s) => [$s->location->sort_order, $s->location->label()])->values(),
            'movements' => $item->movements()->with(['fromLocation', 'toLocation', 'staffMember', 'recorder'])->latest('occurred_at')->latest('id')->paginate(25),
            'canManage' => $this->can($request, $property, 'manager'),
        ]);
    }

    public function edit(Request $request, InventoryItem $item): View
    {
        $property = $this->propertyOf($request, $item, 'manager');

        return view('operations::inventory.items.edit', ['property' => $property, 'item' => $item]);
    }

    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $property = $this->propertyOf($request, $item, 'manager');
        $data = $request->validate($this->rules($property, $item) + ['is_active' => ['nullable', 'boolean']]);

        $item->update($this->attributes($data) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('ops.inventory.items.show', $item)->with('status', 'Item updated.');
    }

    /** Items with history are archived rather than deleted so the movement log stays complete. */
    public function destroy(Request $request, InventoryItem $item): RedirectResponse
    {
        $this->propertyOf($request, $item, 'manager');

        if ($item->movements()->exists()) {
            $item->update(['is_active' => false]);

            return redirect()->route('ops.inventory.items.show', $item)->with('status', 'Item has stock history, so it was archived instead of deleted.');
        }

        $item->delete();

        return redirect()->route('ops.inventory.index')->with('status', 'Item deleted.');
    }

    private function rules(Property $property, ?InventoryItem $item = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('ops_inventory_items', 'name')->where('property_id', $property->id)->ignore($item?->id)],
            'kind' => ['required', Rule::in(array_keys(InventoryItem::KINDS))],
            'category' => ['nullable', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'unit_cost' => ['nullable', self::MONEY],
            'sku' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'kind' => $data['kind'],
            'category' => $data['category'] ?? null,
            'unit' => $data['unit'],
            'reorder_level' => $data['reorder_level'] ?? null,
            'unit_cost_amount' => $this->toMinor($data['unit_cost'] ?? null),
            'sku' => $data['sku'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function locations(Property $property)
    {
        return InventoryLocation::where('property_id', $property->id)->orderBy('sort_order')->orderBy('name')->orderBy('area')->get();
    }
}
