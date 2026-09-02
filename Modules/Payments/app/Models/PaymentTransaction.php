<?php

namespace Modules\Payments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Bookings\Models\Booking;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Events\Models\EventBooking;
use Modules\Services\Models\ServiceOrder;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PaymentTransaction extends Model
{
    use LogsActivity;

    protected $fillable = [
        'booking_id',
        'service_order_id',
        'event_booking_id',
        'payer_id',
        'gateway',
        'reference',
        'gateway_reference',
        'type',
        'amount',
        'currency',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function eventBooking(): BelongsTo
    {
        return $this->belongsTo(EventBooking::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function total(): Money
    {
        return Money::fromMinorUnits($this->amount, Currency::from($this->currency));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'type', 'amount', 'currency', 'gateway'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
