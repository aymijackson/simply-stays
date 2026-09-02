<?php

namespace Modules\Reviews\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Reviews\Models\Review;

class ReviewsController extends Controller
{
    public function index(): View
    {
        $reviews = Review::with(['reviewer', 'reviewable'])->latest()->paginate(20);

        return view('reviews::index', compact('reviews'));
    }

    public function toggle(Review $review): RedirectResponse
    {
        $review->update([
            'status' => $review->status === 'published' ? 'hidden' : 'published',
        ]);

        return back()->with('status', $review->status === 'published' ? 'Review published.' : 'Review hidden.');
    }
}
