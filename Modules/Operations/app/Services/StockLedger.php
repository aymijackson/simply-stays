<?php

namespace Modules\Operations\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Operations\Models\InventoryItem;
use Modules\Operations\Models\InventoryLocation;
use Modules\Operations\Models\InventoryMovement;
use Modules\Operations\Models\InventoryStock;

/**
 * The only place stock levels change.
 *
 * Every change is written as a movement in the same transaction, so a
 * location's quantity always equals the sum of its movements.
 */
class StockLedger
{
    /**
     * @param  array<string, mixed>  $attributes  staff_member_id, recorded_by, occurred_at, note, unit_cost_amount
     *
     * @throws DomainException when the movement is invalid or a location would go below zero
     */
    public static function record(
        InventoryItem $item,
        string $type,
        int $quantity,
        ?InventoryLocation $from = null,
        ?InventoryLocation $to = null,
        array $attributes = [],
    ): InventoryMovement {
        if (! array_key_exists($type, InventoryMovement::TYPES)) {
            throw new DomainException("Unknown movement type [{$type}].");
        }

        [, $needsFrom, $needsTo] = InventoryMovement::TYPES[$type];
        $from = $needsFrom ? $from : null;
        $to = $needsTo ? $to : null;

        if ($needsFrom && ! $from) {
            throw new DomainException('Choose where the items are coming from.');
        }
        if ($needsTo && ! $to) {
            throw new DomainException('Choose where the items are going.');
        }
        if ($from && $to && $from->is($to)) {
            throw new DomainException('Pick two different locations.');
        }
        if ($type === 'adjust' ? $quantity === 0 : $quantity <= 0) {
            throw new DomainException('Enter a quantity greater than zero.');
        }
        foreach ([$from, $to] as $location) {
            if ($location && $location->property_id !== $item->property_id) {
                throw new DomainException('That location belongs to a different property.');
            }
        }

        return DB::transaction(function () use ($item, $type, $quantity, $from, $to, $attributes) {
            if ($from) {
                self::change($item, $from, -$quantity);
            }
            if ($to) {
                self::change($item, $to, $quantity);
            }

            return InventoryMovement::create([
                'property_id' => $item->property_id,
                'item_id' => $item->id,
                'type' => $type,
                'from_location_id' => $from?->id,
                'to_location_id' => $to?->id,
                'quantity' => $quantity,
                'occurred_at' => $attributes['occurred_at'] ?? now(),
            ] + array_intersect_key($attributes, array_flip([
                'unit_cost_amount', 'staff_member_id', 'recorded_by', 'note', 'finance_transaction_id',
            ])));
        });
    }

    /** Record a physical count; writes an adjustment only when the count differs. */
    public static function count(InventoryItem $item, InventoryLocation $location, int $counted, array $attributes = []): ?InventoryMovement
    {
        $current = self::quantity($item, $location);
        $delta = max(0, $counted) - $current;

        if ($delta === 0) {
            return null;
        }

        return self::record($item, 'adjust', $delta, null, $location, $attributes + ['note' => 'Stock count']);
    }

    public static function quantity(InventoryItem $item, InventoryLocation $location): int
    {
        return (int) InventoryStock::where('item_id', $item->id)->where('location_id', $location->id)->value('quantity');
    }

    private static function change(InventoryItem $item, InventoryLocation $location, int $delta): void
    {
        $stock = InventoryStock::query()
            ->where('item_id', $item->id)
            ->where('location_id', $location->id)
            ->lockForUpdate()
            ->first()
            ?? new InventoryStock(['item_id' => $item->id, 'location_id' => $location->id, 'quantity' => 0]);

        $new = $stock->quantity + $delta;

        if ($new < 0) {
            throw new DomainException(sprintf(
                'Only %d %s of "%s" in %s.',
                $stock->quantity,
                $item->unit,
                $item->name,
                $location->label(),
            ));
        }

        $stock->quantity = $new;
        $stock->save();
    }
}
