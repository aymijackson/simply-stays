<?php

namespace Modules\Operations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Listings\Models\Property;

class InventoryMovement extends Model
{
    protected $table = 'ops_inventory_movements';

    /** type => [label, needs "from", needs "to"] */
    public const TYPES = [
        'receive' => ['New stock received', false, true],
        'transfer' => ['Moved between locations', true, true],
        'consume' => ['Used / issued out', true, false],
        'write_off' => ['Damaged, lost or disposed', true, false],
        'adjust' => ['Stock count correction', false, true],
    ];

    protected $fillable = [
        'property_id', 'item_id', 'type', 'from_location_id', 'to_location_id', 'quantity', 'unit_cost_amount',
        'staff_member_id', 'recorded_by', 'occurred_at', 'note', 'finance_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'quantity' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function financeTransaction(): BelongsTo
    {
        return $this->belongsTo(FinanceTransaction::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }
}
