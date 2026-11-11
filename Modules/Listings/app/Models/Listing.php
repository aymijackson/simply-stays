<?php

namespace Modules\Listings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Models\ComplimentaryRule;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\Core\Support\Money;
use Modules\Core\Support\RichText;
use Modules\Core\Traits\Sluggable;
use Modules\Reviews\Models\Review;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Listing extends Model implements HasMedia
{
    use InteractsWithMedia, Sluggable, SoftDeletes;

    protected $fillable = [
        'property_id',
        'title',
        'slug',
        'description',
        'video_url',
        'base_price_amount',
        'currency',
        'max_guests',
        'bedrooms',
        'bathrooms',
        'status',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
        $this->addMediaCollection('videos');
    }

    public function videoUrls(): \Illuminate\Support\Collection
    {
        return $this->getMedia('videos')->map(fn ($media) => $media->getUrl());
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_listing');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function complimentaryRules(): HasMany
    {
        return $this->hasMany(ComplimentaryRule::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function photoUrls(): \Illuminate\Support\Collection
    {
        return $this->getMedia('photos')->map(fn (Media $media) => $media->getUrl());
    }

    public function coverPhotoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photos') ?: null;
    }

    public function price(): Money
    {
        return Money::fromMinorUnits(
            $this->base_price_amount,
            \Modules\Core\Enums\Currency::from($this->currency)
        );
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

    /** Descriptions come from a WYSIWYG editor; sanitise on every write so stored HTML is always safe to render. */
    protected function description(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => RichText::normalize($value));
    }
}
