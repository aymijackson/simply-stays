<?php

namespace Modules\Listings\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Bookings\Models\ComplimentaryRule;
use Modules\Listings\Http\Requests\ComplimentaryRuleRequest;
use Modules\Listings\Models\Listing;
use Modules\Services\Models\Service;

class HostComplimentaryRuleController extends Controller
{
    public function index(Listing $listing): View
    {
        $this->authorizeOwner($listing);

        $rules = $listing->complimentaryRules()->with(['service', 'menuItems'])->get();

        $foodServices = Service::whereHas('category', fn ($q) => $q->where('fulfillment_type', 'food'))
            ->where(function ($q) use ($listing) {
                $q->where('provider_id', Auth::id())
                    ->orWhereHas('properties', fn ($pq) => $pq->where('properties.id', $listing->property_id))
                    ->orWhereDoesntHave('properties');
            })
            ->with('menuItems')
            ->get();

        return view('listings::host.complimentary.index', compact('listing', 'rules', 'foodServices'));
    }

    public function store(ComplimentaryRuleRequest $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwner($listing);

        $validated = $request->validated();

        $rule = $listing->complimentaryRules()->create([
            'service_id' => $validated['service_id'],
            'label' => $validated['label'],
            'min_guests' => $validated['min_guests'],
            'max_quantity_per_guest' => $validated['max_quantity_per_guest'],
            'is_active' => true,
        ]);

        $rule->menuItems()->sync($validated['menu_items']);

        return redirect()->route('host.listings.complimentary.index', $listing)->with('status', 'Complimentary offer created.');
    }

    public function destroy(Listing $listing, ComplimentaryRule $rule): RedirectResponse
    {
        $this->authorizeOwner($listing);
        abort_unless($rule->listing_id === $listing->id, 404);

        $rule->delete();

        return redirect()->route('host.listings.complimentary.index', $listing)->with('status', 'Complimentary offer removed.');
    }

    private function authorizeOwner(Listing $listing): void
    {
        abort_unless(
            $listing->property->host_id === Auth::id() || Auth::user()->hasRole('Admin'),
            403
        );
    }
}
