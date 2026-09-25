<?php

namespace Modules\Bookings\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Bookings\Http\Requests\BookingRequest;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Notifications\BookingCancelled;
use Modules\Bookings\Notifications\BookingConfirmed;
use Modules\Bookings\Notifications\BookingRequested;
use Modules\Bookings\Services\AvailabilityChecker;
use Modules\Core\Support\PricingCalculator;
use Modules\Listings\Models\Listing;
use Modules\Referrals\Services\ReferralAttributor;
use Modules\Reviews\Models\Review;

class BookingController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $asGuest = Booking::with('listing')
            ->where('guest_id', $user->id)
            ->latest()
            ->get();

        $asHost = Booking::with(['listing', 'guest'])
            ->whereHas('listing.property', fn ($query) => $query->where('host_id', $user->id))
            ->latest()
            ->get();

        return view('bookings::index', compact('asGuest', 'asHost'));
    }

    public function create(Listing $listing): View
    {
        abort_unless($listing->status === 'published', 404);

        return view('bookings::create', compact('listing'));
    }

    public function store(BookingRequest $request, ReferralAttributor $referrals): RedirectResponse
    {
        $validated = $request->validated();

        $listing = Listing::findOrFail($validated['listing_id']);
        abort_unless($listing->status === 'published', 404);

        if ($validated['guests_count'] > $listing->max_guests) {
            return back()->withErrors([
                'guests_count' => 'This listing sleeps at most '.$listing->max_guests.' guests.',
            ])->withInput();
        }

        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);

        if (! AvailabilityChecker::isAvailable($listing, $checkIn, $checkOut)) {
            return back()->withErrors([
                'check_in' => 'These dates are no longer available for this listing.',
            ])->withInput();
        }

        $nights = $checkIn->diffInDays($checkOut);
        $subtotal = $listing->base_price_amount * $nights;
        $property = $listing->property;
        $breakdown = PricingCalculator::breakdown(
            $subtotal,
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

        $booking->listing->property->host?->notify(new BookingRequested($booking));

        return redirect()->route('bookings.show', $booking)
            ->with('status', 'Booking request sent. The host will confirm shortly.');
    }

    public function show(Booking $booking): View
    {
        $this->authorizeParticipant($booking);

        $booking->load(['listing.property.host', 'guest']);

        $user = Auth::user();
        $stayHasEnded = $booking->status === 'completed'
            || ($booking->status === 'confirmed' && $booking->check_out->lte(Carbon::today()));

        $canReview = $booking->guest_id === $user->id
            && $stayHasEnded
            && ! Review::where('reviewer_id', $user->id)
                ->where('reviewable_type', $booking->listing::class)
                ->where('reviewable_id', $booking->listing_id)
                ->exists();

        $complimentaryRules = $booking->guest_id === $user->id
            ? $booking->listing->complimentaryRules()
                ->where('is_active', true)
                ->where('min_guests', '<=', $booking->guests_count)
                ->with('menuItems')
                ->get()
                ->reject(fn ($rule) => $booking->hasClaimedComplimentary($rule))
            : collect();

        return view('bookings::show', compact('booking', 'canReview', 'complimentaryRules'));
    }

    public function confirm(Booking $booking): RedirectResponse
    {
        $this->authorizeHost($booking);

        abort_unless($booking->status === 'pending', 422);

        $booking->update(['status' => 'confirmed']);

        $booking->guest->notify(new BookingConfirmed($booking));

        return back()->with('status', 'Booking confirmed.');
    }

    public function cancel(Booking $booking): RedirectResponse
    {
        $this->authorizeParticipant($booking);

        abort_unless(in_array($booking->status, ['pending', 'confirmed']), 422);

        $booking->update(['status' => 'cancelled']);

        $recipient = Auth::id() === $booking->guest_id ? $booking->listing->property->host : $booking->guest;
        $recipient?->notify(new BookingCancelled($booking));

        return back()->with('status', 'Booking cancelled.');
    }

    private function authorizeParticipant(Booking $booking): void
    {
        $user = Auth::user();

        abort_unless(
            $booking->guest_id === $user->id || $booking->listing->property->host_id === $user->id || $user->hasRole('Admin'),
            403
        );
    }

    private function authorizeHost(Booking $booking): void
    {
        $user = Auth::user();

        abort_unless($booking->listing->property->host_id === $user->id || $user->hasRole('Admin'), 403);
    }
}
