<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Core\Traits\HasPublicId;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MenuItem extends Model implements HasMedia
{
    use HasPublicId, InteractsWithMedia;

    protected $fillable = [
        'service_id',
        'name',
        'description',
        'ingredients',
        'allergens',
        'price_amount',
        'currency',
        'category',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'allergens' => 'array',
        ];
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(MenuItemOptionGroup::class);
    }

    public function suggestions(string $type): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_pairings', 'menu_item_id', 'paired_menu_item_id')
            ->withPivot('type')
            ->wherePivot('type', $type);
    }

    public function upsells(): BelongsToMany
    {
        return $this->suggestions('upsell');
    }

    public function pairings(): BelongsToMany
    {
        return $this->suggestions('pairing');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function coverPhotoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photos') ?: null;
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function price(): Money
    {
        return Money::fromMinorUnits($this->price_amount, Currency::from($this->currency));
    }
}
