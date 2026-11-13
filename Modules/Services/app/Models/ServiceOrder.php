<?php

namespace Modules\Services\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Bookings\Models\Booking;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Core\Traits\HasPublicId;
use Modules\Operations\Models\InventoryLocation;
use Modules\Payments\Contracts\Payable;
use Modules\Payments\Models\PaymentTransaction;

class ServiceOrder extends Model implements Payable
{
    use HasPublicId;

    public const REFERENCE_PREFIX = 'SO';

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
        'contact_name',
        'contact_phone',
        'contact_email',
        'location_id',
        'location_label',
        'source',
        'payment_method',
        'public_token',
        'seen_at',
    ];

    public const PAYMENT_METHODS = [
        'online' => 'Pay online',
        'on_delivery' => 'Pay on delivery (cash, POS or transfer)',
        'room_bill' => 'Add to my room bill',
    ];

    protected function casts(): array
    {
        return [
            'fulfillment_details' => 'array',
            'scheduled_at' => 'datetime',
            'price_breakdown' => 'array',
            'is_complimentary' => 'boolean',
            'seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Lets someone without an account follow their order from a private link.
        static::creating(function (self $order) {
            $order->public_token ??= Str::random(40);
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function contactName(): string
    {
        return $this->contact_name ?: ($this->customer?->name ?? 'Guest');
    }

    public function contactEmail(): ?string
    {
        return $this->contact_email ?: $this->customer?->email;
    }

    public function contactPhone(): ?string
    {
        return $this->contact_phone ?: $this->customer?->phone;
    }

    /** Where to deliver: the room, or what the customer typed. */
    public function deliverTo(): ?string
    {
        return $this->location_label ?: ($this->fulfillment_details['delivery_address'] ?? null);
    }

    public function trackingUrl(): string
    {
        return route('qr.track', $this->public_token);
    }

    /** Notify the customer: through their account if they have one, otherwise their email. */
    public function notifyCustomer(BaseNotification $notification): void
    {
        if ($this->customer) {
            $this->customer->notify($notification);
        } elseif ($this->contact_email) {
            Notification::route('mail', [$this->contact_email => $this->contactName()])->notify($notification);
        }
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function items(): HasMany
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

    public function transactions(): HasMany
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
        return 'Service order '.$this->referenceCode().' - '.$this->service->title;
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
