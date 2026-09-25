<?php

namespace Modules\Listings\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Listings\Models\Listing;

class ListingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $listings = Listing::query()
            ->where('status', 'published')
            ->when($request->filled('city'), fn ($query) => $query->whereHas('property', fn ($q) => $q->where('city', 'like', '%'.$request->string('city').'%')))
            ->when($request->filled('guests'), fn ($query) => $query->where('max_guests', '>=', (int) $request->input('guests')))
            ->when($request->filled('min_price'), fn ($query) => $query->where('base_price_amount', '>=', (float) $request->input('min_price') * 100))
            ->when($request->filled('max_price'), fn ($query) => $query->where('base_price_amount', '<=', (float) $request->input('max_price') * 100))
            ->with(['property', 'amenities'])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->published()], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->published()])
            ->latest()
            ->paginate(12);

        return response()->json($listings->through(fn (Listing $listing) => [
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'city' => $listing->property->city,
            'country' => $listing->property->country,
            'price' => $listing->price()->toDecimal(),
            'currency' => $listing->currency,
            'max_guests' => $listing->max_guests,
            'bedrooms' => $listing->bedrooms,
            'bathrooms' => $listing->bathrooms,
            'cover_photo' => $listing->coverPhotoUrl(),
            'amenities' => $listing->amenities->pluck('name'),
            'rating' => $listing->reviews_avg_rating ? round((float) $listing->reviews_avg_rating, 1) : null,
            'reviews_count' => $listing->reviews_count,
        ]));
    }

    public function show(Listing $listing): JsonResponse
    {
        abort_unless($listing->status === 'published', 404);

        $listing->load(['property.host', 'amenities', 'reviews' => fn ($query) => $query->published()->with('reviewer')->latest()]);

        return response()->json([
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'description' => $listing->description,
            'address' => $listing->property->address,
            'city' => $listing->property->city,
            'country' => $listing->property->country,
            'latitude' => $listing->property->latitude,
            'longitude' => $listing->property->longitude,
            'price' => $listing->price()->toDecimal(),
            'currency' => $listing->currency,
            'max_guests' => $listing->max_guests,
            'bedrooms' => $listing->bedrooms,
            'bathrooms' => $listing->bathrooms,
            'host' => ['name' => $listing->property->hostDisplayName()],
            'amenities' => $listing->amenities->pluck('name'),
            'photos' => $listing->photoUrls(),
            'video_url' => $listing->video_url,
            'videos' => $listing->videoUrls(),
            'rating' => $listing->averageRating(),
            'reviews_count' => $listing->reviewsCount(),
            'reviews' => $listing->reviews->map(fn ($review) => [
                'rating' => $review->rating,
                'comment' => $review->comment,
                'reviewer' => $review->reviewer->name,
            ]),
        ]);
    }
}
