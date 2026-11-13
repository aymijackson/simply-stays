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
use Modules\Listings\Models\Property;
use Modules\Services\Http\Requests\ServiceRequest;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Spatie\Permission\Models\Role;

class ProviderServiceController extends Controller
{
    public function index(Request $request): View
    {
        $isAdmin = Auth::user()->hasRole('Admin');

        // Providers manage their own services; admins oversee everyone's.
        $services = Service::with(['category', 'properties', 'provider'])
            ->when(! $isAdmin, fn ($q) => $q->where('provider_id', Auth::id()))
            ->when($isAdmin && $request->filled('provider'), fn ($q) => $q->where('provider_id', $request->integer('provider')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->input('q').'%'))
            ->latest()
            ->get();

        $providers = $isAdmin
            ? User::whereIn('id', Service::select('provider_id'))->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('services::provider.index', compact('services', 'isAdmin', 'providers'));
    }

    public function create(Request $request): View
    {
        $categories = ServiceCategory::orderBy('name')->get();
        $properties = Property::orderBy('name')->get();
        $vendors = $this->vendorOptions();
        $selectedVendorId = $request->integer('provider') ?: null;

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

        $owner = $service->provider_id === Auth::id() ? '' : " for {$service->provider->name}";

        return redirect()->route('provider.services.index')->with('status', "Service created{$owner}. Publish it when you're ready to take orders.");
    }

    public function edit(Service $service): View
    {
        $this->authorizeOwner($service);

        $categories = ServiceCategory::orderBy('name')->get();
        $properties = Property::orderBy('name')->get();
        $selectedPropertyIds = $service->properties->pluck('id')->all();
        $vendors = $this->vendorOptions();
        $selectedVendorId = $service->provider_id;

        return view('services::provider.edit', compact('service', 'categories', 'properties', 'selectedPropertyIds', 'vendors', 'selectedVendorId'));
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        $validated = $request->validated();

        // Only an admin can move a service to another vendor; everyone else keeps it as is.
        $service->update($this->payload($validated) + array_filter(['provider_id' => $this->chosenProvider($validated, $service)]));

        $service->properties()->sync($validated['properties'] ?? []);

        return redirect()->route('provider.services.edit', $service)->with('status', 'Service updated.');
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

    /** The vendor an admin picked, validated to be a vendor or the admin themselves; null otherwise. */
    private function chosenProvider(array $validated, ?Service $current = null): ?int
    {
        if (! Auth::user()->hasRole('Admin') || empty($validated['provider_id'])) {
            return null;
        }

        $id = (int) $validated['provider_id'];
        if ($current && $id === $current->provider_id) {
            return null; // unchanged
        }
        $user = User::findOrFail($id);

        if ($id !== Auth::id() && ! $user->hasRole('ServiceProvider')) {
            throw ValidationException::withMessages(['provider_id' => 'Pick a registered vendor.']);
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
