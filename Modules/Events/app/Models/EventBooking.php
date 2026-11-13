<?php

namespace Modules\Events\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Core\Traits\HasPublicId;
use Modules\Payments\Contracts\Payable;
use Modules\Payments\Models\PaymentTransaction;

class EventBooking extends Model implements Payable
{
    use HasPublicId;

    public const REFERENCE_PREFIX = 'EB';

    protected $fillable = [
        'event_occurrence_id',
        'guest_id',
        'quantity',
        'status',
        'total_amount',
        'currency',
    ];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function total(): Money
    {
        return Money::fromMinorUnits($this->total_amount, Currency::from($this->currency));
    }

    public function isPaid(): bool
    {
        return $this->transactions()->where('type', 'charge')->where('status', 'succeeded')->exists();
    }

    public function payerId(): int
    {
        return $this->guest_id;
    }

    public function paymentDescription(): string
    {
        return 'Event ticket - '.$this->occurrence->event->title;
    }

    public function paymentAmountMinorUnits(): int
    {
        return $this->total_amount;
    }

    public function paymentCurrency(): string
    {
        return $this->currency;
    }
}
