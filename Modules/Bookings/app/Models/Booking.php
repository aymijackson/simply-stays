<?php

namespace Modules\Bookings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Listings\Models\Listing;
use Modules\Payments\Contracts\Payable;
use Modules\Payments\Models\PaymentTransaction;
use Modules\Services\Models\ServiceOrder;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model implements Payable
{
    use LogsActivity;

    protected $fillable = [
        'listing_id',
        'guest_id',
        'check_in',
        'check_out',
        'guests_count',
        'status',
        'total_amount',
        'currency',
        'referral_code',
        'price_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'price_breakdown' => 'array',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function hasClaimedComplimentary(ComplimentaryRule $rule): bool
    {
        return $this->serviceOrders()
            ->where('service_id', $rule->service_id)
            ->where('is_complimentary', true)
            ->exists();
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function nights(): int
    {
        return $this->check_in->diffInDays($this->check_out);
    }

    public function deliveryAddressLabel(): string
    {
        $property = $this->listing->property;

        return trim("{$this->listing->title}, {$property->address}, {$property->city}");
    }

    public function total(): Money
    {
        return Money::fromMinorUnits($this->total_amount, Currency::from($this->currency));
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
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
        return 'Booking #'.$this->id.' - '.$this->listing->title;
    }

    public function paymentAmountMinorUnits(): int
    {
        return $this->total_amount;
    }

    public function paymentCurrency(): string
    {
        return $this->currency;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
