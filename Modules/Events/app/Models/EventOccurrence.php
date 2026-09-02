<?php

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventOccurrence extends Model
{
    protected $fillable = [
        'event_id',
        'date',
        'start_time',
        'end_time',
        'capacity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EventBooking::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')->where('date', '>=', now()->toDateString());
    }

    public function ticketsSold(): int
    {
        return (int) $this->bookings()->whereIn('status', ['pending', 'confirmed'])->sum('quantity');
    }

    public function remainingCapacity(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->ticketsSold());
    }
}
