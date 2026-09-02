<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Services\Http\Requests\MenuItemOptionGroupRequest;
use Modules\Services\Http\Requests\MenuItemOptionRequest;
use Modules\Services\Models\MenuItem;
use Modules\Services\Models\MenuItemOption;
use Modules\Services\Models\MenuItemOptionGroup;
use Modules\Services\Models\Service;

class ProviderMenuItemOptionController extends Controller
{
    public function index(Service $service, MenuItem $menuItem): View
    {
        $this->authorizeOwner($service, $menuItem);

        $groups = $menuItem->optionGroups()->with('options')->get();

        return view('services::provider.menu-items.options', compact('service', 'menuItem', 'groups'));
    }

    public function storeGroup(MenuItemOptionGroupRequest $request, Service $service, MenuItem $menuItem): RedirectResponse
    {
        $this->authorizeOwner($service, $menuItem);

        $validated = $request->validated();

        $menuItem->optionGroups()->create([
            'name' => $validated['name'],
            'selection_type' => $validated['selection_type'],
            'is_required' => $request->boolean('is_required'),
            'min_select' => $validated['min_select'] ?? 0,
            'max_select' => $validated['max_select'] ?? null,
        ]);

        return redirect()->route('provider.services.menu-items.options.index', [$service, $menuItem])->with('status', 'Option group added.');
    }

    public function destroyGroup(Service $service, MenuItem $menuItem, MenuItemOptionGroup $group): RedirectResponse
    {
        $this->authorizeOwner($service, $menuItem);
        abort_unless($group->menu_item_id === $menuItem->id, 404);

        $group->delete();

        return redirect()->route('provider.services.menu-items.options.index', [$service, $menuItem])->with('status', 'Option group removed.');
    }

    public function storeOption(MenuItemOptionRequest $request, Service $service, MenuItem $menuItem, MenuItemOptionGroup $group): RedirectResponse
    {
        $this->authorizeOwner($service, $menuItem);
        abort_unless($group->menu_item_id === $menuItem->id, 404);

        $validated = $request->validated();

        $group->options()->create([
            'name' => $validated['name'],
            'price_delta_amount' => isset($validated['price_delta'])
                ? Money::fromDecimal((float) $validated['price_delta'], Currency::from($menuItem->currency))->minorUnits
                : 0,
            'max_quantity' => $validated['max_quantity'] ?? 1,
            'is_default' => $request->boolean('is_default'),
            'is_available' => $request->boolean('is_available', true),
        ]);

        return redirect()->route('provider.services.menu-items.options.index', [$service, $menuItem])->with('status', 'Option added.');
    }

    public function destroyOption(Service $service, MenuItem $menuItem, MenuItemOptionGroup $group, MenuItemOption $option): RedirectResponse
    {
        $this->authorizeOwner($service, $menuItem);
        abort_unless($group->menu_item_id === $menuItem->id, 404);
        abort_unless($option->menu_item_option_group_id === $group->id, 404);

        $option->delete();

        return redirect()->route('provider.services.menu-items.options.index', [$service, $menuItem])->with('status', 'Option removed.');
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
