<?php

namespace Modules\Events\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Core\Traits\Sluggable;
use Modules\Listings\Models\Property;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Event extends Model implements HasMedia
{
    use InteractsWithMedia, Sluggable, SoftDeletes;

    /** URLs use the slug, never the database id. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'created_by',
        'title',
        'slug',
        'description',
        'property_id',
        'location_address',
        'category',
        'is_ticketed',
        'capacity',
        'price_amount',
        'currency',
        'commission_type',
        'commission_value',
        'recurrence_type',
        'recurrence_days_of_week',
        'starts_on',
        'ends_on',
        'start_time',
        'end_time',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_ticketed' => 'boolean',
            'recurrence_days_of_week' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function photoUrls(): Collection
    {
        return $this->getMedia('photos')->map(fn ($media) => $media->getUrl());
    }

    public function coverPhotoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photos') ?: null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function price(): Money
    {
        return Money::fromMinorUnits($this->price_amount, Currency::from($this->currency));
    }

    public function venueLabel(): string
    {
        return $this->property?->name ?? $this->location_address ?? 'Location to be announced';
    }

    public function recurrenceSummary(): string
    {
        return match ($this->recurrence_type) {
            'daily' => 'Every day',
            'weekly' => 'Every '.collect($this->recurrence_days_of_week ?? [])
                ->map(fn ($day) => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day] ?? '')
                ->filter()
                ->join(', '),
            'monthly' => 'Monthly on day '.$this->starts_on->day,
            default => $this->starts_on->format('M j, Y'),
        };
    }

    public function canEditRecurrence(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval', 'rejected']);
    }
}
