<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Services\Http\Requests\MenuItemPairingRequest;
use Modules\Services\Models\MenuItem;
use Modules\Services\Models\MenuItemPairing;
use Modules\Services\Models\Service;

class ProviderMenuItemPairingController extends Controller
{
    public function index(Service $service, MenuItem $menuItem): View
    {
        $this->authorizeOwner($service, $menuItem);

        $pairings = MenuItemPairing::with('pairedMenuItem')
            ->where('menu_item_id', $menuItem->id)
            ->get();

        $otherItems = $service->menuItems()->where('id', '!=', $menuItem->id)->get();

        return view('services::provider.menu-items.pairings', compact('service', 'menuItem', 'pairings', 'otherItems'));
    }

    public function store(MenuItemPairingRequest $request, Service $service, MenuItem $menuItem): RedirectResponse
    {
        $this->authorizeOwner($service, $menuItem);

        $validated = $request->validated();

        abort_if((int) $validated['paired_menu_item_id'] === $menuItem->id, 422, 'A meal cannot be paired with itself.');

        $paired = $service->menuItems()->findOrFail($validated['paired_menu_item_id']);

        MenuItemPairing::firstOrCreate([
            'menu_item_id' => $menuItem->id,
            'paired_menu_item_id' => $paired->id,
            'type' => $validated['type'],
        ]);

        return redirect()->route('provider.services.menu-items.pairings.index', [$service, $menuItem])->with('status', 'Suggestion added.');
    }

    public function destroy(Service $service, MenuItem $menuItem, MenuItemPairing $pairing): RedirectResponse
    {
        $this->authorizeOwner($service, $menuItem);
        abort_unless($pairing->menu_item_id === $menuItem->id, 404);

        $pairing->delete();

        return redirect()->route('provider.services.menu-items.pairings.index', [$service, $menuItem])->with('status', 'Suggestion removed.');
    }

    private function authorizeOwner(Service $service, MenuItem $menuItem): void
    {
        abort_unless($menuItem->service_id === $service->id, 404);

        abort_unless(
            $service->provider_id === Auth::id() || Auth::user()->hasRole('Admin'),
            403
        );
    }
}
