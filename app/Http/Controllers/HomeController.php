<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Modules\Listings\Models\Listing;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;

class HomeController extends Controller
{
    public function index(): View
    {
        $featuredListings = Listing::query()
            ->where('status', 'published')
            ->with('property')
            ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->published()], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->published()])
            ->latest()
            ->take(6)
            ->get();

        $featuredServices = Service::query()
            ->where('status', 'published')
            ->with('category')
            ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->published()], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->published()])
            ->latest()
            ->take(3)
            ->get();

        $categories = ServiceCategory::orderBy('name')->get();

        return view('home', compact('featuredListings', 'featuredServices', 'categories'));
    }
}
