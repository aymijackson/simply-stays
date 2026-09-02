<?php

namespace Modules\Reviews\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Bookings\Models\Booking;
use Modules\Reviews\Http\Requests\ReviewRequest;
use Modules\Reviews\Models\Review;

class ListingReviewController extends Controller
{
    public function create(Booking $booking): View
    {
        $this->authorizeEligible($booking);

        return view('reviews::create', [
            'title' => $booking->listing->title,
            'actionUrl' => route('bookings.review.store', $booking),
        ]);
    }

    public function store(ReviewRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorizeEligible($booking);

        $validated = $request->validated();

        Review::create([
            'reviewable_type' => $booking->listing::class,
            'reviewable_id' => $booking->listing_id,
            'reviewer_id' => Auth::id(),
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'status' => 'published',
        ]);

        return redirect()->route('bookings.show', $booking)->with('status', 'Thanks for your review!');
    }

    private function authorizeEligible(Booking $booking): void
    {
        abort_unless($booking->guest_id === Auth::id(), 403);

        $stayHasEnded = $booking->status === 'completed'
            || ($booking->status === 'confirmed' && $booking->check_out->lte(Carbon::today()));

        abort_unless($stayHasEnded, 422, 'You can review a stay once it has ended.');

        $alreadyReviewed = Review::where('reviewer_id', Auth::id())
            ->where('reviewable_type', $booking->listing::class)
            ->where('reviewable_id', $booking->listing_id)
            ->exists();

        abort_if($alreadyReviewed, 422, 'You have already reviewed this listing.');
    }
}
