<?php

namespace Modules\Bookings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Listings\Models\Listing;
use Modules\Services\Models\MenuItem;
use Modules\Services\Models\Service;

class ComplimentaryRule extends Model
{
    protected $fillable = [
        'listing_id',
        'service_id',
        'label',
        'min_guests',
        'max_quantity_per_guest',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'complimentary_rule_menu_item');
    }

    public function isEligibleFor(int $guestsCount): bool
    {
        return $this->is_active && $guestsCount >= $this->min_guests;
    }

    public function allowance(int $guestsCount): int
    {
        return $guestsCount * $this->max_quantity_per_guest;
    }
}
