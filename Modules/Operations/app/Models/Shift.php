<?php

namespace Modules\Operations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Listings\Models\Property;

class Shift extends Model
{
    protected $table = 'ops_shifts';

    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'missed', 'cancelled'];

    protected $fillable = [
        'property_id', 'staff_member_id', 'starts_at', 'ends_at', 'area', 'notes', 'status',
        'clock_in_at', 'clock_out_at', 'handover_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scheduledMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /** Minutes actually worked, from clock-in to clock-out (or now while still on duty). */
    public function workedMinutes(): int
    {
        if (! $this->clock_in_at) {
            return 0;
        }

        return (int) $this->clock_in_at->diffInMinutes($this->clock_out_at ?? now());
    }

    public function isOnDuty(): bool
    {
        return $this->clock_in_at !== null && $this->clock_out_at === null;
    }

    /** Clock-in opens 2 hours before the shift and closes when the shift ends. */
    public function canClockIn(): bool
    {
        return $this->clock_in_at === null
            && $this->status === 'scheduled'
            && now()->between($this->starts_at->copy()->subHours(2), $this->ends_at);
    }

    public function lateMinutes(): int
    {
        if (! $this->clock_in_at || $this->clock_in_at->lte($this->starts_at)) {
            return 0;
        }

        return (int) $this->starts_at->diffInMinutes($this->clock_in_at);
    }

    public static function formatMinutes(int $minutes): string
    {
        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }
}
