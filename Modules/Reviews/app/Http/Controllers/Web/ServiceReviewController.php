<?php

namespace Modules\Reviews\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Reviews\Http\Requests\ReviewRequest;
use Modules\Reviews\Models\Review;
use Modules\Services\Models\ServiceOrder;

class ServiceReviewController extends Controller
{
    public function create(ServiceOrder $order): View
    {
        $this->authorizeEligible($order);

        return view('reviews::create', [
            'title' => $order->service->title,
            'actionUrl' => route('service-orders.review.store', $order),
        ]);
    }

    public function store(ReviewRequest $request, ServiceOrder $order): RedirectResponse
    {
        $this->authorizeEligible($order);

        $validated = $request->validated();

        Review::create([
            'reviewable_type' => $order->service::class,
            'reviewable_id' => $order->service_id,
            'reviewer_id' => Auth::id(),
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'status' => 'published',
        ]);

        return redirect()->route('service-orders.show', $order)->with('status', 'Thanks for your review!');
    }

    private function authorizeEligible(ServiceOrder $order): void
    {
        abort_unless($order->customer_id === Auth::id(), 403);

        abort_unless($order->status === 'completed', 422, 'You can review a service once it has been completed.');

        $alreadyReviewed = Review::where('reviewer_id', Auth::id())
            ->where('reviewable_type', $order->service::class)
            ->where('reviewable_id', $order->service_id)
            ->exists();

        abort_if($alreadyReviewed, 422, 'You have already reviewed this service.');
    }
}
