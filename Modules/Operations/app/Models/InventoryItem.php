<?php

namespace Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Core\Traits\HasPublicId;
use Modules\Listings\Models\Property;

class InventoryItem extends Model
{
    use HasPublicId;

    protected $table = 'ops_inventory_items';

    public const KINDS = [
        'asset' => 'Asset',
        'consumable' => 'Consumable',
        'supply' => 'Daily supply',
    ];

    public const KIND_HINTS = [
        'asset' => 'Durable things you keep and count: TVs, fridges, rugs, plates, linen.',
        'consumable' => 'Used up while running the place: gas, detergent, food, cleaning chemicals.',
        'supply' => 'Replenished for guests every stay: toiletries, tissue, soap, tooth brushes.',
    ];

    public const CATEGORIES = [
        'Electronics & appliances', 'Furniture & fittings', 'Kitchenware', 'Linen & towels', 'Decor & flowers',
        'Cleaning', 'Toiletries & amenities', 'Food & beverage', 'Laundry', 'Books & stationery', 'Safety & security',
        'Uniforms', 'Other',
    ];

    public const UNITS = ['pcs', 'pack', 'set', 'pair', 'bottle', 'carton', 'roll', 'litre', 'kg', 'bundle'];

    protected $fillable = [
        'property_id', 'name', 'kind', 'category', 'unit', 'reorder_level', 'unit_cost_amount', 'currency', 'sku', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'reorder_level' => 'integer',
            'unit_cost_amount' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id');
    }

    public function unitCost(): ?Money
    {
        return $this->unit_cost_amount === null ? null : Money::fromMinorUnits($this->unit_cost_amount, Currency::from($this->currency));
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }
}
