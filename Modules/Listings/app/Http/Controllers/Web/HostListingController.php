<?php

namespace Modules\Listings\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Listings\Http\Requests\ListingRequest;
use Modules\Listings\Models\Amenity;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HostListingController extends Controller
{
    public function index(): View
    {
        $listings = Listing::with('property')
            ->whereHas('property', fn ($query) => $query->where('host_id', Auth::id()))
            ->latest()
            ->get();

        return view('listings::host.index', compact('listings'));
    }

    public function create(): View
    {
        $amenities = Amenity::orderBy('name')->get();
        $properties = Property::where('host_id', Auth::id())->orderBy('name')->get();

        return view('listings::host.create', compact('amenities', 'properties'));
    }

    public function store(ListingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $property = Property::where('host_id', Auth::id())->findOrFail($validated['property_id']);

        $listing = Listing::create([
            'property_id' => $property->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'video_url' => $validated['video_url'] ?? null,
            'base_price_amount' => Money::fromDecimal((float) $validated['price'], Currency::from($validated['currency']))->minorUnits,
            'currency' => $validated['currency'],
            'max_guests' => $validated['max_guests'],
            'bedrooms' => $validated['bedrooms'],
            'bathrooms' => $validated['bathrooms'],
            'status' => 'draft',
        ]);

        $listing->amenities()->sync($validated['amenities'] ?? []);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $listing->addMedia($photo)->toMediaCollection('photos');
            }
        }

        if ($request->hasFile('video')) {
            $listing->addMedia($request->file('video'))->toMediaCollection('videos');
        }

        return redirect()->route('host.listings.index')->with('status', 'Listing created. Publish it when you\'re ready for guests to see it.');
    }

    public function edit(Listing $listing): View
    {
        $this->authorizeOwner($listing);

        $amenities = Amenity::orderBy('name')->get();
        $properties = Property::where('host_id', Auth::id())->orderBy('name')->get();
        $selectedAmenityIds = $listing->amenities->pluck('id')->all();

        return view('listings::host.edit', compact('listing', 'amenities', 'properties', 'selectedAmenityIds'));
    }

    public function update(ListingRequest $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwner($listing);

        $validated = $request->validated();

        $property = Property::where('host_id', Auth::id())->findOrFail($validated['property_id']);

        $listing->update([
            'property_id' => $property->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'video_url' => $validated['video_url'] ?? null,
            'base_price_amount' => Money::fromDecimal((float) $validated['price'], Currency::from($validated['currency']))->minorUnits,
            'currency' => $validated['currency'],
            'max_guests' => $validated['max_guests'],
            'bedrooms' => $validated['bedrooms'],
            'bathrooms' => $validated['bathrooms'],
        ]);

        $listing->amenities()->sync($validated['amenities'] ?? []);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $listing->addMedia($photo)->toMediaCollection('photos');
            }
        }

        if ($request->hasFile('video')) {
            $listing->addMedia($request->file('video'))->toMediaCollection('videos');
        }

        return redirect()->route('host.listings.edit', $listing)->with('status', 'Listing updated.');
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        $this->authorizeOwner($listing);

        $listing->delete();

        return redirect()->route('host.listings.index')->with('status', 'Listing removed.');
    }

    public function publish(Listing $listing): RedirectResponse
    {
        $this->authorizeOwner($listing);

        $listing->update([
            'status' => $listing->status === 'published' ? 'draft' : 'published',
        ]);

        return redirect()->route('host.listings.index')->with('status', $listing->status === 'published' ? 'Listing published.' : 'Listing unpublished.');
    }

    public function destroyPhoto(Listing $listing, Media $media): RedirectResponse
    {
        $this->authorizeOwner($listing);

        abort_unless($media->model_id === $listing->id, 404);

        $media->delete();

        return redirect()->route('host.listings.edit', $listing)->with('status', 'Photo removed.');
    }

    private function authorizeOwner(Listing $listing): void
    {
        abort_unless(
            $listing->property->host_id === Auth::id() || Auth::user()->hasRole('Admin'),
            403
        );
    }
}
