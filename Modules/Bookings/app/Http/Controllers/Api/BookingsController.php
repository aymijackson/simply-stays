<?php

namespace Modules\Bookings\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Bookings\Http\Requests\BookingRequest;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Notifications\BookingRequested;
use Modules\Bookings\Services\AvailabilityChecker;
use Modules\Core\Support\PricingCalculator;
use Modules\Listings\Models\Listing;
use Modules\Referrals\Services\ReferralAttributor;

class BookingsController extends Controller
{
    public function index(): JsonResponse
    {
        $bookings = Booking::with('listing')
            ->where('guest_id', Auth::id())
            ->latest()
            ->get()
            ->map($this->transform(...));

        return response()->json($bookings);
    }

    public function store(BookingRequest $request, ReferralAttributor $referrals): JsonResponse
    {
        $validated = $request->validated();

        $listing = Listing::findOrFail($validated['listing_id']);
        abort_unless($listing->status === 'published', 404);

        if ($validated['guests_count'] > $listing->max_guests) {
            return response()->json(['message' => 'This listing sleeps at most '.$listing->max_guests.' guests.'], 422);
        }

        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);

        if (! AvailabilityChecker::isAvailable($listing, $checkIn, $checkOut)) {
            return response()->json(['message' => 'These dates are not available for this listing.'], 422);
        }

        $nights = $checkIn->diffInDays($checkOut);
        $property = $listing->property;
        $breakdown = PricingCalculator::breakdown(
            $listing->base_price_amount * $nights,
            (float) $property->service_charge_percent,
            (float) $property->tax_percent,
        );

        $booking = Booking::create([
            'listing_id' => $listing->id,
            'guest_id' => Auth::id(),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'guests_count' => $validated['guests_count'],
            'status' => 'pending',
            'total_amount' => $breakdown['total'],
            'currency' => $listing->currency,
            'price_breakdown' => $breakdown,
        ]);

        $booking->load(['guest', 'listing.property.host']);

        $referrals->attributeForBooking($booking, $validated['referral_code'] ?? null);

        $booking->listing->property->host->notify(new BookingRequested($booking));

        return response()->json($this->transform($booking), 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        $user = Auth::user();

        abort_unless(
            $booking->guest_id === $user->id || $booking->listing->property->host_id === $user->id || $user->hasRole('Admin'),
            403
        );

        return response()->json($this->transform($booking->load('listing.property')));
    }

    private function transform(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'listing' => [
                'id' => $booking->listing->id,
                'title' => $booking->listing->title,
                'slug' => $booking->listing->slug,
            ],
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->toDateString(),
            'nights' => $booking->nights(),
            'guests_count' => $booking->guests_count,
            'status' => $booking->status,
            'total' => $booking->total()->toDecimal(),
            'currency' => $booking->currency,
        ];
    }
}
