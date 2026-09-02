<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Services\Http\Requests\MenuItemRequest;
use Modules\Services\Models\MenuItem;
use Modules\Services\Models\Service;

class ProviderMenuItemController extends Controller
{
    public function index(Service $service): View
    {
        $this->authorizeOwner($service);

        $menuItems = $service->menuItems()->with('optionGroups')->latest()->get();

        return view('services::provider.menu-items.index', compact('service', 'menuItems'));
    }

    public function store(MenuItemRequest $request, Service $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        $validated = $request->validated();

        $menuItem = $service->menuItems()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'ingredients' => $validated['ingredients'] ?? null,
            'allergens' => $this->parseAllergens($validated['allergens'] ?? null),
            'price_amount' => Money::fromDecimal((float) $validated['price'], Currency::from($service->currency))->minorUnits,
            'currency' => $service->currency,
            'category' => $validated['category'] ?? null,
            'is_available' => $request->boolean('is_available', true),
        ]);

        if ($request->hasFile('photo')) {
            $menuItem->addMedia($request->file('photo'))->toMediaCollection('photos');
        }

        return redirect()->route('provider.services.menu-items.index', $service)->with('status', 'Menu item added.');
    }

    public function update(MenuItemRequest $request, Service $service, MenuItem $menuItem): RedirectResponse
    {
        $this->authorizeOwner($service);
        abort_unless($menuItem->service_id === $service->id, 404);

        $validated = $request->validated();

        $menuItem->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'ingredients' => $validated['ingredients'] ?? null,
            'allergens' => $this->parseAllergens($validated['allergens'] ?? null),
            'price_amount' => Money::fromDecimal((float) $validated['price'], Currency::from($service->currency))->minorUnits,
            'category' => $validated['category'] ?? null,
            'is_available' => $request->boolean('is_available', true),
        ]);

        if ($request->hasFile('photo')) {
            $menuItem->addMedia($request->file('photo'))->toMediaCollection('photos');
        }

        return redirect()->route('provider.services.menu-items.index', $service)->with('status', 'Menu item updated.');
    }

    private function parseAllergens(?string $raw): ?array
    {
        if (! $raw) {
            return null;
        }

        $allergens = array_filter(array_map('trim', explode(',', $raw)));

        return empty($allergens) ? null : array_values($allergens);
    }

    public function destroy(Service $service, MenuItem $menuItem): RedirectResponse
    {
        $this->authorizeOwner($service);
        abort_unless($menuItem->service_id === $service->id, 404);

        $menuItem->delete();

        return redirect()->route('provider.services.menu-items.index', $service)->with('status', 'Menu item removed.');
    }

    private function authorizeOwner(Service $service): void
    {
        abort_unless(
            $service->provider_id === Auth::id() || Auth::user()->hasRole('Admin'),
            403
        );
    }
}
