<?php

namespace Modules\Listings\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Listings\Models\Listing;

class ListingsController extends Controller
{
    public function index(Request $request): View
    {
        $listings = Listing::query()
            ->where('status', 'published')
            ->when($request->filled('city'), fn ($query) => $query->whereHas('property', fn ($q) => $q->where('city', 'like', '%'.$request->string('city').'%')))
            ->when($request->filled('guests'), fn ($query) => $query->where('max_guests', '>=', (int) $request->input('guests')))
            ->when($request->filled('min_price'), fn ($query) => $query->where('base_price_amount', '>=', (float) $request->input('min_price') * 100))
            ->when($request->filled('max_price'), fn ($query) => $query->where('base_price_amount', '<=', (float) $request->input('max_price') * 100))
            ->with('property')
            ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->published()], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->published()])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('listings::index', compact('listings'));
    }

    public function show(Listing $listing): View
    {
        abort_unless($listing->status === 'published', 404);

        $listing->load([
            'property.host',
            'amenities',
            'complimentaryRules.menuItems',
            'reviews' => fn ($query) => $query->published()->with('reviewer')->latest(),
        ]);

        $siblingListings = $listing->property->listings()
            ->published()
            ->where('id', '!=', $listing->id)
            ->get();

        $availableServices = $listing->property->services()
            ->where('status', 'published')
            ->with('category')
            ->get()
            ->merge(
                \Modules\Services\Models\Service::where('status', 'published')
                    ->doesntHave('properties')
                    ->with('category')
                    ->get()
            );

        return view('listings::show', compact('listing', 'siblingListings', 'availableServices'));
    }
}
