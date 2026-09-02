<?php

namespace Modules\Bookings\Services;

use Carbon\CarbonInterface;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;

class AvailabilityChecker
{
    public static function isAvailable(Listing $listing, CarbonInterface $checkIn, CarbonInterface $checkOut, ?int $excludeBookingId = null): bool
    {
        return ! Booking::query()
            ->where('listing_id', $listing->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->when($excludeBookingId, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->where('check_in', '<', $checkOut->toDateString())
            ->where('check_out', '>', $checkIn->toDateString())
            ->exists();
    }
}
