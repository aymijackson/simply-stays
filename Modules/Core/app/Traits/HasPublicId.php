<?php

namespace Modules\Core\Traits;

use Illuminate\Support\Str;

/**
 * Gives a model an unguessable public key (a lower-case ULID) and uses it in
 * URLs instead of the database id, so records can't be found by counting
 * (/bookings/12, /bookings/13 ...). route('x', $model) and route-model binding
 * pick it up automatically.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            $model->public_id ??= self::newPublicId();
        });
    }

    public static function newPublicId(): string
    {
        return strtolower((string) Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Short code to show people instead of "#12" (which reveals how many records
     * exist), e.g. "SO-7F3K9Q2M". Models set REFERENCE_PREFIX for the part before
     * the dash. For display only: look records up by public_id, not by this.
     */
    public function referenceCode(): string
    {
        $prefix = defined(static::class.'::REFERENCE_PREFIX') ? constant(static::class.'::REFERENCE_PREFIX').'-' : '';

        return $prefix.strtoupper(substr((string) $this->public_id, -8));
    }
}
