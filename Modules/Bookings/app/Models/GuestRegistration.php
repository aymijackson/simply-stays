<?php

namespace Modules\Bookings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\HasPublicId;
use Modules\Listings\Models\Property;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class GuestRegistration extends Model implements HasMedia
{
    use HasPublicId, InteractsWithMedia;

    public const REFERENCE_PREFIX = 'GR';

    /** Hand-filled scans hold personal data, so they live on the private disk and are streamed through an authorised route. */
    public const SCANS_DISK = 'local';

    protected $fillable = [
        'property_id',
        'booking_id',
        'guest_id',
        'created_by',
        'guest_name',
        'check_in',
        'check_out',
        'data',
        'signature',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'check_in' => 'date',
            'check_out' => 'date',
            'signed_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('scans')
            ->useDisk(self::SCANS_DISK)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function value(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function isStaff(User $user): bool
    {
        return $user->hasRole('Admin') || ($this->property?->host_id !== null && $this->property->host_id === $user->id);
    }

    public function canView(User $user): bool
    {
        return $this->isStaff($user) || $this->guest_id === $user->id || $this->created_by === $user->id;
    }
}
