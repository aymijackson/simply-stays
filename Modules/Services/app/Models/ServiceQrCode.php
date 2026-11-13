<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Operations\Models\InventoryLocation;

/**
 * A printable QR code for ordering a service. Generic codes ask the guest
 * where to deliver; room codes have the room (an apartment location) set.
 */
class ServiceQrCode extends Model
{
    protected $fillable = ['service_id', 'location_id', 'token', 'is_active'];

    // Mirror the column defaults so a code created in this request reads correctly.
    protected $attributes = ['is_active' => true, 'scans' => 0];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_scanned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $code) {
            $code->token ??= Str::lower(Str::random(12));
        });
    }

    /** URLs use the random token, never the database id. */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public static function for(Service $service, ?InventoryLocation $location = null): self
    {
        return self::firstOrCreate(['service_id' => $service->id, 'location_id' => $location?->id]);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function url(): string
    {
        return route('qr.order', $this->token);
    }

    public function label(): string
    {
        return $this->location?->label() ?? 'Any location';
    }
}
