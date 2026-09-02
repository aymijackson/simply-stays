<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Services\Models\MenuItem;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;

class ServicesController extends Controller
{
    public function index(Request $request): View
    {
        $categories = ServiceCategory::orderBy('name')->get();

        $services = Service::query()
            ->where('status', 'published')
            ->when($request->filled('category'), fn ($query) => $query->where('service_category_id', $request->input('category')))
            ->when($request->filled('property'), fn ($query) => $query->where(function ($q) use ($request) {
                $q->whereHas('properties', fn ($pq) => $pq->where('properties.id', $request->input('property')))
                    ->orWhereDoesntHave('properties');
            }))
            ->with(['category', 'menuItems'])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->published()], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->published()])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('services::index', compact('services', 'categories'));
    }

    public function show(Service $service): View
    {
        abort_unless($service->status === 'published', 404);

        $service->load(['category', 'provider', 'menuItems', 'reviews' => fn ($query) => $query->published()->with('reviewer')->latest()]);

        return view('services::show', compact('service'));
    }

    public function menuItem(Service $service, MenuItem $menuItem): View
    {
        abort_unless($service->status === 'published', 404);
        abort_unless($menuItem->service_id === $service->id, 404);

        $menuItem->load('optionGroups.options');

        return view('services::menu-item-show', compact('service', 'menuItem'));
    }
}
