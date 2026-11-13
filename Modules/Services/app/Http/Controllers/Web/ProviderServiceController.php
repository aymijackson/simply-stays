<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Core\Support\RouteKey;
use Modules\Listings\Models\Property;
use Modules\Services\Http\Requests\ServiceRequest;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;

class ProviderServiceController extends Controller
{
    public function index(Request $request): View
    {
        $isAdmin = Auth::user()->hasRole('Admin');

        // Providers manage their own services; admins oversee everyone's.
        $provider = $request->input('provider');
        $inHouse = fn ($q) => $q->whereHas('provider', fn ($p) => $p->whereHas('roles', fn ($r) => $r->where('name', 'Admin')));

        $services = Service::with(['category', 'properties', 'provider.roles', 'media'])
            ->when(! $isAdmin, fn ($q) => $q->where('provider_id', Auth::id()))
            ->when($isAdmin && $provider === 'in_house', $inHouse)
            ->when($isAdmin && $provider && $provider !== 'in_house', fn ($q) => $q->where('provider_id', RouteKey::id(User::class, $provider) ?? 0))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->input('q').'%'))
            ->latest()
            ->get();

        // Vendors only; in-house services have their own filter option.
        $providers = $isAdmin
            ? User::whereIn('id', Service::select('provider_id'))
                ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'Admin'))
                ->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('services::provider.index', compact('services', 'isAdmin', 'providers'));
    }

    public function create(Request $request): View
    {
        $categories = ServiceCategory::orderBy('name')->get();
        $properties = Property::orderBy('name')->get();
        $vendors = $this->vendorOptions();
        $selectedVendorId = RouteKey::id(User::class, $request->query('provider')) ?? 'in_house';

        return view('services::provider.create', compact('categories', 'properties', 'vendors', 'selectedVendorId'));
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $service = Service::create($this->payload($validated) + [
            'provider_id' => $this->chosenProvider($validated) ?? Auth::id(),
            'status' => 'draft',
        ]);

        $service->properties()->sync($validated['properties'] ?? []);
        $this->storePhotos($request, $service);

        $owner = match (true) {
            $service->isInHouse() => ' as an in-house service',
            $service->provider_id === Auth::id() => '',
            default => " for {$service->provider->name}",
        };

        return redirect()->route('provider.services.index')->with('status', "Service created{$owner}. Publish it when you're ready to take orders.");
    }

    public function edit(Service $service): View
    {
        $this->authorizeOwner($service);

        $categories = ServiceCategory::orderBy('name')->get();
        $properties = Property::orderBy('name')->get();
        $selectedPropertyIds = $service->properties->pluck('id')->all();
        $vendors = $this->vendorOptions();
        $selectedVendorId = $service->isInHouse() ? 'in_house' : $service->provider_id;

        return view('services::provider.edit', compact('service', 'categories', 'properties', 'selectedPropertyIds', 'vendors', 'selectedVendorId'));
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        $validated = $request->validated();

        // Only an admin can move a service to another vendor; everyone else keeps it as is.
        $service->update($this->payload($validated) + array_filter(['provider_id' => $this->chosenProvider($validated, $service)]));

        $service->properties()->sync($validated['properties'] ?? []);
        $this->storePhotos($request, $service);

        return redirect()->route('provider.services.edit', $service)->with('status', 'Service updated.');
    }

    public function destroyPhoto(Service $service, Media $media): RedirectResponse
    {
        $this->authorizeOwner($service);
        $media->delete();

        return redirect()->route('provider.services.edit', $service)->with('status', 'Photo removed.');
    }

    /** Move a photo to the front so it becomes the cover on cards and the service page. */
    public function coverPhoto(Service $service, Media $media): RedirectResponse
    {
        $this->authorizeOwner($service);

        $order = $service->getMedia('photos')->pluck('id')->reject(fn ($id) => $id === $media->id)->prepend($media->id)->all();
        Media::setNewOrder($order);

        return redirect()->route('provider.services.edit', $service)->with('status', 'Cover photo updated.');
    }

    private function storePhotos(ServiceRequest $request, Service $service): void
    {
        foreach ($request->file('photos', []) as $photo) {
            $service->addMedia($photo)->toMediaCollection('photos');
        }
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        $service->delete();

        return redirect()->route('provider.services.index')->with('status', 'Service removed.');
    }

    public function publish(Service $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        $service->update([
            'status' => $service->status === 'published' ? 'draft' : 'published',
        ]);

        return redirect()->route('provider.services.index')
            ->with('status', $service->status === 'published' ? 'Service published.' : 'Service unpublished.');
    }

    private function payload(array $validated): array
    {
        $currency = Currency::from($validated['currency']);

        // No provider_id here: updates must keep the service with its provider,
        // even when an admin is the one saving it.
        $payload = [
            'service_category_id' => $validated['service_category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'price_amount' => Money::fromDecimal((float) $validated['price'], $currency)->minorUnits,
            'currency' => $validated['currency'],
            'audience' => $validated['audience'],
            'delivery_fee_amount' => isset($validated['delivery_fee']) ? Money::fromDecimal((float) $validated['delivery_fee'], $currency)->minorUnits : 0,
            'free_delivery_over_amount' => isset($validated['free_delivery_over']) && $validated['free_delivery_over'] !== ''
                ? Money::fromDecimal((float) $validated['free_delivery_over'], $currency)->minorUnits
                : null,
        ];

        foreach (['service_charge_percent', 'tax_percent', 'other_charge_percent'] as $rate) {
            $payload[$rate] = ($validated[$rate] ?? '') === '' ? null : $validated[$rate];
        }
        $payload['other_charge_label'] = $payload['other_charge_percent'] !== null ? ($validated['other_charge_label'] ?? null) : null;

        if (Auth::user()->hasRole('Admin')) {
            $payload['commission_type'] = $validated['commission_type'] ?? 'none';
            $payload['commission_value'] = $validated['commission_value'] ?? null;
        }

        return $payload;
    }

    /** Vendors an admin can assign a service to (empty for non-admins, who never see the picker). */
    private function vendorOptions()
    {
        if (! Auth::user()->hasRole('Admin')) {
            return collect();
        }

        Role::findOrCreate('ServiceProvider', 'web');

        return User::role('ServiceProvider')->orderBy('name')->get(['id', 'name', 'email', 'status']);
    }

    /**
     * The owner an admin picked: "in_house" (run by the property, owned by an admin account)
     * or a registered vendor's id. Null means "leave as it is" / not an admin.
     */
    private function chosenProvider(array $validated, ?Service $current = null): ?int
    {
        $choice = $validated['provider_id'] ?? null;

        if (! Auth::user()->hasRole('Admin') || $choice === null || $choice === '') {
            return null;
        }

        if ($choice === 'in_house') {
            // Already in-house under another admin: keep it there rather than moving it to whoever saved.
            return $current?->isInHouse() ? null : Auth::id();
        }

        $id = (int) $choice;
        if ($current && $id === $current->provider_id) {
            return null;
        }

        if (! User::find($id)?->hasRole('ServiceProvider')) {
            throw ValidationException::withMessages(['provider_id' => 'Pick a registered vendor, or In-house.']);
        }

        return $id;
    }

    private function authorizeOwner(Service $service): void
    {
        abort_unless(
            $service->provider_id === Auth::id() || Auth::user()->hasRole('Admin'),
            403
        );
    }
}
