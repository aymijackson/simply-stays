<?php

namespace Modules\Operations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Bookings\Models\Booking;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Listings\Models\Property;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FinanceTransaction extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'ops_finance_transactions';

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'transfer' => 'Bank transfer',
        'pos' => 'POS',
        'card' => 'Card (online)',
        'cheque' => 'Cheque',
        'other' => 'Other',
    ];

    protected $fillable = [
        'property_id', 'category_id', 'type', 'amount', 'currency', 'occurred_on', 'payment_method', 'counterparty',
        'reference', 'description', 'booking_id', 'staff_member_id', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Receipts can carry bank and personal details, so they never go on the public disk.
        $this->addMediaCollection('receipts')->useDisk('local');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function money(): Money
    {
        return Money::fromMinorUnits($this->amount, Currency::from($this->currency));
    }
}
