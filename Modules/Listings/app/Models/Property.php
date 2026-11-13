<?php

namespace Modules\Listings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Modules\Core\Traits\Sluggable;
use Modules\Services\Models\Service;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Property extends Model implements HasMedia
{
    use InteractsWithMedia, Sluggable, SoftDeletes;

    /** URLs use the slug, never the database id. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'host_id',
        'name',
        'slug',
        'description',
        'property_type',
        'address',
        'city',
        'country',
        'latitude',
        'longitude',
        'service_charge_percent',
        'tax_percent',
    ];

    public function slugSourceColumn(): string
    {
        return 'name';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function photoUrls(): Collection
    {
        return $this->getMedia('photos')->map(fn ($media) => $media->getUrl());
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function hostDisplayName(): string
    {
        return $this->host?->name ?? $this->name;
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'property_service');
    }
}
