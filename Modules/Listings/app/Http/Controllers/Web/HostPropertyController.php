<?php

namespace Modules\Listings\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Listings\Http\Requests\PropertyRequest;
use Modules\Listings\Models\Property;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HostPropertyController extends Controller
{
    public function index(): View
    {
        $properties = $this->availableProperties()
            ->with('host')
            ->withCount('listings')
            ->latest()
            ->get();

        return view('listings::host.properties.index', compact('properties'));
    }

    public function create(): View
    {
        return view('listings::host.properties.create');
    }

    public function store(PropertyRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $property = Property::create([
            'host_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'property_type' => $validated['property_type'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'country' => $validated['country'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'service_charge_percent' => $validated['service_charge_percent'] ?? 0,
            'tax_percent' => $validated['tax_percent'] ?? 0,
        ]);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $property->addMedia($photo)->toMediaCollection('photos');
            }
        }

        return redirect()->route('host.properties.index')->with('status', 'Property created. Add unit types (listings) to it next.');
    }

    public function edit(Property $property): View
    {
        $this->authorizeOwner($property);

        $hostUsers = Auth::user()->hasRole('Admin')
            ? User::role('Host')->orderBy('name')->get()
            : null;

        return view('listings::host.properties.edit', compact('property', 'hostUsers'));
    }

    public function update(PropertyRequest $request, Property $property): RedirectResponse
    {
        $this->authorizeOwner($property);

        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'property_type' => $validated['property_type'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'country' => $validated['country'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'service_charge_percent' => $validated['service_charge_percent'] ?? 0,
            'tax_percent' => $validated['tax_percent'] ?? 0,
        ];

        if (Auth::user()->hasRole('Admin')) {
            $updateData['host_id'] = $validated['host_id'] ?? null;
        }

        $property->update($updateData);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $property->addMedia($photo)->toMediaCollection('photos');
            }
        }

        return redirect()->route('host.properties.edit', $property)->with('status', 'Property updated.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $this->authorizeOwner($property);

        abort_if($property->listings()->exists(), 422, 'Remove or reassign this property\'s listings before deleting it.');

        $property->delete();

        return redirect()->route('host.properties.index')->with('status', 'Property removed.');
    }

    public function destroyPhoto(Property $property, Media $media): RedirectResponse
    {
        $this->authorizeOwner($property);

        abort_unless($media->model_id === $property->id, 404);

        $media->delete();

        return redirect()->route('host.properties.edit', $property)->with('status', 'Photo removed.');
    }

    private function authorizeOwner(Property $property): void
    {
        abort_unless(
            $property->host_id === Auth::id() || Auth::user()->hasRole('Admin'),
            403
        );
    }

    private function availableProperties(): Builder
    {
        $query = Property::query();

        if (! Auth::user()->hasRole('Admin')) {
            $query->where('host_id', Auth::id());
        }

        return $query;
    }
}
