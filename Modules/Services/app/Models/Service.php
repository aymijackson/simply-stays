<?php

namespace Modules\Services\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Core\Traits\DecodesPlainText;
use Modules\Core\Traits\Sluggable;
use Modules\Listings\Models\Property;
use Modules\Reviews\Models\Review;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Service extends Model implements HasMedia
{
    use DecodesPlainText, InteractsWithMedia, Sluggable;

    /** Saved as plain text: stray HTML entities like &amp; are decoded. */
    protected array $plainTextFields = ['title'];

    /** URLs use the slug (from the title), never the database id. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'provider_id',
        'service_category_id',
        'title',
        'description',
        'price_amount',
        'currency',
        'status',
        'audience',
        'delivery_fee_amount',
        'free_delivery_over_amount',
        'free_delivery_windows',
        'commission_type',
        'commission_value',
        'service_charge_percent',
        'tax_percent',
        'other_charge_label',
        'other_charge_percent',
    ];

    protected function casts(): array
    {
        return [
            'free_delivery_windows' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    /** First photo is the cover shown on cards; null means show placeholder art. */
    public function coverPhotoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photos') ?: null;
    }

    /** @return Collection<int, string> */
    public function photoUrls(): Collection
    {
        return $this->getMedia('photos')->map(fn ($media) => $media->getUrl());
    }

    /**
     * Priced item by item (food menu, laundry price list) rather than one flat price.
     * A laundry service with no price list yet still sells at its flat price.
     */
    public function usesPriceList(): bool
    {
        return match ($this->category?->fulfillment_type) {
            'food' => true,
            'laundry' => $this->menuItems()->exists(),
            default => false,
        };
    }

    public function isLaundry(): bool
    {
        return $this->category?->fulfillment_type === 'laundry';
    }

    /**
     * Charge rates for an order: the service's own rate where set, otherwise the
     * property's (when the order has a property), otherwise none.
     *
     * @return array{service_charge: float, tax: float, other: float, other_label: ?string}
     */
    public function chargeRates(?Property $property): array
    {
        $pick = fn ($own, $fallback) => $own !== null ? (float) $own : (float) ($fallback ?? 0);

        return [
            'service_charge' => $pick($this->service_charge_percent, $property?->service_charge_percent),
            'tax' => $pick($this->tax_percent, $property?->tax_percent),
            'other' => (float) ($this->other_charge_percent ?? 0),
            'other_label' => $this->other_charge_percent ? ($this->other_charge_label ?: 'Other charge') : null,
        ];
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(ServiceQrCode::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /** Run by the property itself rather than a vendor: owned by an admin (platform) account. */
    public function isInHouse(): bool
    {
        return (bool) $this->provider?->hasRole('Admin');
    }

    /**
     * The name guests see as the provider. Vendors show their own name; in-house
     * services show the property (never the admin account that holds them): the
     * order's property when known, else the property the service is limited to,
     * else the site's name.
     */
    public function offeredBy(?Property $context = null): string
    {
        if (! $this->isInHouse()) {
            return $this->provider?->name ?? config('app.name');
        }

        if ($context) {
            return $context->name;
        }

        $this->loadMissing('properties');

        return match ($this->properties->count()) {
            0 => config('app.name'),
            1 => $this->properties->first()->name,
            default => $this->properties->pluck('name')->join(', ', ' & '),
        };
    }

    public function providerLabel(): string
    {
        return $this->isInHouse() ? 'In-house' : ($this->provider?->name ?? 'Deleted account');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_service');
    }

    public function requiresBooking(): bool
    {
        return $this->audience === 'guests_only';
    }

    public function allowsBookingLink(): bool
    {
        return $this->audience !== 'non_guests_only';
    }

    public function isAvailableForProperty(?int $propertyId): bool
    {
        if (! $this->relationLoaded('properties')) {
            $this->load('properties');
        }

        if ($this->properties->isEmpty()) {
            return true;
        }

        return $propertyId !== null && $this->properties->contains('id', $propertyId);
    }

    public function price(): Money
    {
        return Money::fromMinorUnits($this->price_amount, Currency::from($this->currency));
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function averageRating(): ?float
    {
        $average = $this->reviews()->published()->avg('rating');

        return $average !== null ? round((float) $average, 1) : null;
    }

    public function reviewsCount(): int
    {
        return $this->reviews()->published()->count();
    }
}
