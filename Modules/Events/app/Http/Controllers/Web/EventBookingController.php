<?php

namespace Modules\Events\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Events\Http\Requests\EventBookingRequest;
use Modules\Events\Models\EventBooking;
use Modules\Events\Models\EventOccurrence;

class EventBookingController extends Controller
{
    public function store(EventBookingRequest $request, EventOccurrence $occurrence): RedirectResponse
    {
        $event = $occurrence->event;

        abort_unless($event->status === 'published', 404);
        abort_unless($event->is_ticketed, 422, 'This event does not require a booking.');
        abort_unless($occurrence->status === 'scheduled', 422, 'This occurrence is no longer available.');

        $quantity = (int) $request->validated('quantity');

        $booking = DB::transaction(function () use ($occurrence, $quantity, $event) {
            $locked = EventOccurrence::whereKey($occurrence->id)->lockForUpdate()->firstOrFail();

            if ($locked->capacity !== null && $locked->remainingCapacity() < $quantity) {
                abort(422, 'Not enough tickets remaining for this date.');
            }

            $totalAmount = $event->price_amount * $quantity;

            return EventBooking::create([
                'event_occurrence_id' => $locked->id,
                'guest_id' => Auth::id(),
                'quantity' => $quantity,
                'status' => $totalAmount > 0 ? 'pending' : 'confirmed',
                'total_amount' => $totalAmount,
                'currency' => $event->currency,
            ]);
        });

        return redirect()->route('event-bookings.show', $booking)
            ->with('status', $booking->status === 'confirmed'
                ? 'You\'re booked in!'
                : 'Reservation held. Complete payment to confirm your tickets.');
    }

    public function show(EventBooking $eventBooking): View
    {
        $this->authorizeParticipant($eventBooking);

        $eventBooking->load(['occurrence.event.creator', 'guest']);

        return view('events::booking-show', ['booking' => $eventBooking]);
    }

    public function cancel(EventBooking $eventBooking): RedirectResponse
    {
        $this->authorizeParticipant($eventBooking);

        abort_unless(in_array($eventBooking->status, ['pending', 'confirmed']), 422);

        $eventBooking->update(['status' => 'cancelled']);

        return back()->with('status', 'Booking cancelled.');
    }

    private function authorizeParticipant(EventBooking $eventBooking): void
    {
        $user = Auth::user();

        abort_unless(
            $eventBooking->guest_id === $user->id || $eventBooking->occurrence->event->created_by === $user->id || $user->hasRole('Admin'),
            403
        );
    }
}
