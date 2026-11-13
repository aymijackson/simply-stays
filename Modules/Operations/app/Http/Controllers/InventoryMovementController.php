<?php

namespace Modules\Operations\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Core\Support\RouteKey;
use Modules\Operations\Models\FinanceCategory;
use Modules\Operations\Models\FinanceTransaction;
use Modules\Operations\Models\InventoryItem;
use Modules\Operations\Models\InventoryLocation;
use Modules\Operations\Models\InventoryMovement;
use Modules\Operations\Models\StaffMember;
use Modules\Operations\Services\StockLedger;
use Modules\Operations\Support\OpsContext;

class InventoryMovementController extends OpsController
{
    public function index(Request $request): View
    {
        $property = $this->property($request, 'inventory');

        $query = InventoryMovement::with(['item', 'fromLocation', 'toLocation', 'staffMember', 'recorder'])
            ->where('property_id', $property->id)
            ->latest('occurred_at')->latest('id');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('location')) {
            $id = RouteKey::id(InventoryLocation::class, $request->query('location'), InventoryLocation::where('property_id', $property->id)) ?? 0;
            $query->where(fn ($q) => $q->where('from_location_id', $id)->orWhere('to_location_id', $id));
        }
        if ($request->filled('q')) {
            $query->whereHas('item', fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'));
        }
        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->date('to'));
        }

        return view('operations::inventory.movements.index', [
            'property' => $property,
            'movements' => $query->paginate(40)->withQueryString(),
            'locations' => $this->locations($property->id),
        ]);
    }

    public function create(Request $request): View
    {
        $property = $this->property($request, 'inventory');

        return view('operations::inventory.movements.create', [
            'property' => $property,
            'type' => array_key_exists($request->input('type'), InventoryMovement::TYPES) ? $request->input('type') : 'transfer',
            'items' => InventoryItem::where('property_id', $property->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit', 'unit_cost_amount']),
            'locations' => $this->locations($property->id),
            'staff' => StaffMember::where('property_id', $property->id)->active()->orderBy('name')->get(['id', 'name', 'position']),
            'presetItem' => RouteKey::id(InventoryItem::class, $request->query('item'), InventoryItem::where('property_id', $property->id)),
            'presetFrom' => RouteKey::id(InventoryLocation::class, $request->query('from'), InventoryLocation::where('property_id', $property->id)),
            'presetTo' => RouteKey::id(InventoryLocation::class, $request->query('to'), InventoryLocation::where('property_id', $property->id)),
            'canManage' => $this->can($request, $property, 'manager'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $property = $this->property($request, 'inventory');
        $canManage = $this->can($request, $property, 'manager');
        $locationRule = Rule::exists('ops_inventory_locations', 'id')->where('property_id', $property->id);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(InventoryMovement::TYPES))],
            'from_location_id' => ['nullable', $locationRule],
            'to_location_id' => ['nullable', $locationRule],
            'staff_member_id' => ['nullable', Rule::exists('ops_staff_members', 'id')->where('property_id', $property->id)],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.item_id' => ['nullable', Rule::exists('ops_inventory_items', 'id')->where('property_id', $property->id)],
            'lines.*.quantity' => ['nullable', 'integer', 'between:-1000000,1000000'],
            'lines.*.unit_cost' => ['nullable', self::MONEY],
            'post_expense' => ['nullable', 'boolean'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'payment_method' => ['nullable', Rule::in(array_keys(FinanceTransaction::PAYMENT_METHODS))],
        ]);

        $lines = collect($data['lines'])->filter(fn ($l) => ! empty($l['item_id']) && ! empty($l['quantity']))->values();
        if ($lines->isEmpty()) {
            $this->fail('lines', 'Add at least one item with a quantity.');
        }

        $from = isset($data['from_location_id']) ? InventoryLocation::find($data['from_location_id']) : null;
        $to = isset($data['to_location_id']) ? InventoryLocation::find($data['to_location_id']) : null;
        $occurredAt = $data['occurred_at'] ?? now();
        $postExpense = $canManage && $data['type'] === 'receive' && $request->boolean('post_expense');

        try {
            DB::transaction(function () use ($lines, $data, $from, $to, $occurredAt, $postExpense, $property, $request) {
                $expense = null;
                if ($postExpense) {
                    $total = $lines->sum(fn ($l) => (int) $l['quantity'] * (int) $this->toMinor($l['unit_cost'] ?? null));
                    if ($total > 0) {
                        $names = InventoryItem::whereIn('id', $lines->pluck('item_id'))->pluck('name');
                        $expense = FinanceTransaction::create([
                            'property_id' => $property->id,
                            'category_id' => FinanceCategory::bySlug('inventory-purchases')->id,
                            'type' => 'expense',
                            'amount' => $total,
                            'currency' => OpsContext::currency(),
                            'occurred_on' => Carbon::parse($occurredAt)->toDateString(),
                            'payment_method' => $data['payment_method'] ?? 'cash',
                            'counterparty' => $data['supplier'] ?? null,
                            'description' => 'Stock purchase: '.$names->implode(', '),
                            'recorded_by' => $request->user()->id,
                        ]);
                    }
                }

                foreach ($lines as $line) {
                    $item = InventoryItem::find($line['item_id']);
                    $cost = $this->toMinor($line['unit_cost'] ?? null);

                    StockLedger::record($item, $data['type'], (int) $line['quantity'], $from, $to, [
                        'occurred_at' => $occurredAt,
                        'staff_member_id' => $data['staff_member_id'] ?? null,
                        'recorded_by' => $request->user()->id,
                        'note' => $data['note'] ?? null,
                        'unit_cost_amount' => $cost,
                        'finance_transaction_id' => $expense?->id,
                    ]);

                    // Keep the catalogue's unit cost current with the latest purchase price.
                    if ($data['type'] === 'receive' && $cost) {
                        $item->update(['unit_cost_amount' => $cost]);
                    }
                }
            });
        } catch (DomainException $e) {
            $this->fail('lines', $e->getMessage());
        }

        $count = $lines->count();

        return redirect()->route('ops.inventory.movements.index')->with('status', "{$count} stock movement".($count === 1 ? '' : 's').' recorded.');
    }

    private function locations(int $propertyId)
    {
        return InventoryLocation::where('property_id', $propertyId)->orderBy('sort_order')->orderBy('name')->orderBy('area')->get();
    }
}
