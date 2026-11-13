<?php

namespace Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\HasPublicId;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;

class InventoryLocation extends Model
{
    use HasPublicId;

    protected $table = 'ops_inventory_locations';

    public const TYPES = [
        'store' => 'Store',
        'apartment' => 'Apartment / suite',
        'kitchen' => 'Kitchen',
        'laundry' => 'Laundry',
        'reception' => 'Reception',
        'staff' => 'Staff quarters',
        'other' => 'Other',
    ];

    public const AREAS = ['Living room', 'Bedroom', 'Bedroom 2', 'Kitchen', 'Toilet', 'Room toilet', 'Balcony'];

    protected $fillable = ['property_id', 'listing_id', 'name', 'area', 'type', 'sort_order', 'notes'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'location_id');
    }

    public function label(): string
    {
        return $this->area ? "{$this->name} — {$this->area}" : $this->name;
    }

    public function isStore(): bool
    {
        return $this->type === 'store';
    }
}
