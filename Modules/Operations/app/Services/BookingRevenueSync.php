<?php

namespace Modules\Operations\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\FinanceCategory;
use Modules\Operations\Models\FinanceTransaction;
use Modules\Operations\Support\OpsContext;

/**
 * Posts confirmed/completed platform bookings into the property's books as
 * room revenue. Safe to run repeatedly: a booking is only ever posted once.
 */
class BookingRevenueSync
{
    public static function pending(Property $property): Builder
    {
        return Booking::query()
            ->whereHas('listing', fn (Builder $q) => $q->where('property_id', $property->id))
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('currency', OpsContext::currency())
            ->whereNotIn('id', FinanceTransaction::query()->whereNotNull('booking_id')->select('booking_id'));
    }

    public static function sync(Property $property, ?int $recordedBy = null): int
    {
        $category = FinanceCategory::bySlug('room-revenue');
        $posted = 0;

        self::pending($property)->with(['guest', 'listing'])->chunkById(100, function ($bookings) use ($property, $category, $recordedBy, &$posted) {
            foreach ($bookings as $booking) {
                FinanceTransaction::create([
                    'property_id' => $property->id,
                    'category_id' => $category->id,
                    'type' => 'income',
                    'amount' => $booking->total_amount,
                    'currency' => $booking->currency,
                    'occurred_on' => $booking->check_in,
                    'payment_method' => 'card',
                    'counterparty' => $booking->guest?->name,
                    'reference' => 'Booking '.$booking->referenceCode(),
                    'description' => sprintf(
                        '%s, %s to %s (%d night%s)',
                        $booking->listing->title,
                        $booking->check_in->format('j M'),
                        $booking->check_out->format('j M Y'),
                        $booking->nights(),
                        $booking->nights() === 1 ? '' : 's',
                    ),
                    'booking_id' => $booking->id,
                    'recorded_by' => $recordedBy,
                ]);
                $posted++;
            }
        });

        return $posted;
    }
}
