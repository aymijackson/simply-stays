<?php

namespace Modules\Services\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Bookings\Models\Booking;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Payments\Contracts\Payable;
use Modules\Payments\Models\PaymentTransaction;

class ServiceOrder extends Model implements Payable
{
    protected $fillable = [
        'service_id',
        'customer_id',
        'booking_id',
        'status',
        'fulfillment_details',
        'scheduled_at',
        'total_amount',
        'currency',
        'price_breakdown',
        'is_complimentary',
    ];

    protected function casts(): array
    {
        return [
            'fulfillment_details' => 'array',
            'scheduled_at' => 'datetime',
            'price_breakdown' => 'array',
            'is_complimentary' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isPaid(): bool
    {
        return $this->transactions()->where('type', 'charge')->where('status', 'succeeded')->exists();
    }

    public function total(): Money
    {
        return Money::fromMinorUnits($this->total_amount, Currency::from($this->currency));
    }

    public function payerId(): int
    {
        return $this->customer_id;
    }

    public function paymentDescription(): string
    {
        return 'Service order #'.$this->id.' - '.$this->service->title;
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
