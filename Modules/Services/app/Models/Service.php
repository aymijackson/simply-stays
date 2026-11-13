<?php

namespace Modules\Services\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Listings\Models\Property;
use Modules\Reviews\Models\Review;

class Service extends Model
{
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
