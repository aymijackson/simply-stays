<?php

namespace Modules\Bookings\Support;

use App\Models\User;
use Modules\Bookings\Models\Booking;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;

/**
 * Builds the initial `data` for a new registration form from what the
 * platform already knows: the guest's account and, when the form is
 * started from a booking, the reservation and payment records.
 */
class GuestRegistrationPrefill
{
    /**
     * @return array<string, string|int|null>
     */
    public static function build(?Booking $booking, ?User $guest): array
    {
        $data = [
            'units' => 1,
            'ack_date' => now()->toDateString(),
        ];

        if ($guest) {
            $data += [
                'full_name' => $guest->name,
                'ack_name' => $guest->name,
                'email' => $guest->email,
                'mobile' => $guest->phone,
            ];
        }

        if (! $booking) {
            return $data;
        }

        $booking->loadMissing(['listing', 'transactions']);
        $currency = Currency::from($booking->currency);
        $nights = $booking->nights();

        $succeeded = $booking->transactions->where('status', 'succeeded');
        $paidMinor = $succeeded->where('type', 'charge')->sum('amount') - $succeeded->where('type', 'refund')->sum('amount');
        $paidMinor = max(0, (int) $paidMinor);
        $outstandingMinor = max(0, $booking->total_amount - $paidMinor);
        $lastCharge = $succeeded->where('type', 'charge')->sortByDesc('id')->first();

        $subtotal = $booking->price_breakdown['subtotal'] ?? $booking->total_amount;
        $extras = $booking->total_amount - $subtotal;

        return array_merge($data, [
            'adults' => $booking->guests_count,
            'reference' => 'BK-'.str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT),
            'reserved_on' => $booking->created_at?->toDateString(),
            'unit_type' => $booking->listing->title,
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->toDateString(),
            'nights' => $nights,
            'channel' => config('app.name').' (online)',
            'nightly_rate' => $nights > 0 ? Money::fromMinorUnits(intdiv($subtotal, $nights), $currency)->format() : null,
            'charge_nights' => $nights,
            'additional_charges' => $extras > 0 ? Money::fromMinorUnits($extras, $currency)->format().' (service charge & tax)' : null,
            'total_amount' => $booking->total()->format(),
            'amount_paid' => Money::fromMinorUnits($paidMinor, $currency)->format(),
            'outstanding' => Money::fromMinorUnits($outstandingMinor, $currency)->format(),
            'payment_status' => match (true) {
                $paidMinor === 0 => 'Unpaid',
                $outstandingMinor === 0 => 'Paid',
                default => 'Part-paid',
            },
            'payment_method' => $lastCharge ? ucfirst($lastCharge->gateway) : null,
            'payment_reference' => $lastCharge?->reference,
        ]);
    }
}
