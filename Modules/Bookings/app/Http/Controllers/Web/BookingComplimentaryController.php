<?php

namespace Modules\Bookings\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Models\ComplimentaryRule;
use Modules\Services\Models\ServiceOrder;

class BookingComplimentaryController extends Controller
{
    public function store(Request $request, Booking $booking, ComplimentaryRule $rule): RedirectResponse
    {
        abort_unless($booking->guest_id === Auth::id(), 403);
        abort_unless($rule->listing_id === $booking->listing_id, 404);
        abort_unless($rule->isEligibleFor($booking->guests_count), 422, 'This booking is not eligible for this offer.');
        abort_if($booking->hasClaimedComplimentary($rule), 422, 'You have already claimed this offer.');

        $selections = collect($request->input('items', []))
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn ($quantity) => $quantity > 0);

        abort_if($selections->isEmpty(), 422, 'Select at least one item to claim.');

        $allowance = $rule->allowance($booking->guests_count);
        abort_if($selections->sum() > $allowance, 422, "You can claim at most {$allowance} item(s).");

        $eligibleItems = $rule->menuItems()->whereIn('menu_items.id', $selections->keys())->get()->keyBy('id');
        abort_unless($selections->keys()->every(fn ($id) => $eligibleItems->has($id)), 422, 'One or more selected items are not eligible for this offer.');

        DB::transaction(function () use ($booking, $rule, $selections, $eligibleItems) {
            $order = ServiceOrder::create([
                'service_id' => $rule->service_id,
                'customer_id' => $booking->guest_id,
                'booking_id' => $booking->id,
                'status' => 'confirmed',
                'fulfillment_details' => ['notes' => $rule->label],
                'total_amount' => 0,
                'currency' => $eligibleItems->first()->currency,
                'is_complimentary' => true,
            ]);

            foreach ($selections as $menuItemId => $quantity) {
                $item = $eligibleItems->get($menuItemId);

                $order->items()->create([
                    'menu_item_id' => $item->id,
                    'name' => $item->name,
                    'unit_price_amount' => $item->price_amount,
                    'quantity' => $quantity,
                    'is_complimentary' => true,
                    'line_total_amount' => 0,
                ]);
            }
        });

        return back()->with('status', $rule->label.' claimed! Check your service orders for details.');
    }
}
