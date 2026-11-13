<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Bookings\Models\Booking;
use Modules\Reviews\Models\Review;
use Modules\Services\Fulfillment\ServiceFulfillmentManager;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceOrder;
use Modules\Services\Models\ServiceOrderItem;
use Modules\Services\Support\OrderBuilder;
use Modules\Services\Support\OrderNotifier;
use Modules\Services\Support\PriceList;

class ServiceOrderController extends Controller
{
    public function create(Service $service, ServiceFulfillmentManager $handlers): View
    {
        abort_unless($service->status === 'published', 404);

        $handler = $handlers->driver($service->category->fulfillment_type);
        $isFood = $service->category->fulfillment_type === 'food';
        $menuItems = $isFood
            ? $service->menuItems()
                ->where('is_available', true)
                ->with(['optionGroups.options', 'upsells', 'pairings'])
                ->get()
            : collect();

        $orderedBeforeIds = $isFood
            ? ServiceOrderItem::whereHas('order', fn ($q) => $q->where('service_id', $service->id)->where('customer_id', Auth::id()))
                ->whereNotNull('menu_item_id')
                ->distinct()
                ->pluck('menu_item_id')
            : collect();

        $orderedBefore = $menuItems->whereIn('id', $orderedBeforeIds);

        $requiresBooking = $service->requiresBooking();
        $allowsBookingLink = $service->allowsBookingLink();

        $myBookings = $allowsBookingLink
            ? Booking::with('listing.property')
                ->where('guest_id', Auth::id())
                ->whereIn('status', ['pending', 'confirmed'])
                ->get()
                ->filter(fn (Booking $booking) => $service->isAvailableForProperty($booking->listing->property_id))
            : collect();

        $priceList = PriceList::for($service);

        return view('services::order', compact('service', 'handler', 'myBookings', 'isFood', 'menuItems', 'orderedBefore', 'requiresBooking', 'allowsBookingLink', 'priceList'));
    }

    public function store(Request $request, Service $service, ServiceFulfillmentManager $handlers): RedirectResponse
    {
        abort_unless($service->status === 'published', 404);

        $handler = $handlers->driver($service->category->fulfillment_type);
        $isFood = $service->category->fulfillment_type === 'food';
        $requiresBooking = $service->requiresBooking();
        $allowsBookingLink = $service->allowsBookingLink();

        $rules = [
            'booking_id' => $requiresBooking ? ['required', 'exists:bookings,id'] : ['nullable', 'exists:bookings,id'],
        ];

        foreach ($handler->validationRules() as $field => $fieldRules) {
            // When a booking is required, its address stands in for a manually
            // typed delivery address, so don't demand one from the guest.
            if ($requiresBooking && $field === 'delivery_address') {
                continue;
            }

            $rules["details.{$field}"] = $fieldRules;
        }

        if ($isFood) {
            $rules['items'] = ['required', 'array'];
            $rules['items.*.quantity'] = ['nullable', 'integer', 'min:0'];
        }

        if ($service->isLaundry() && $service->usesPriceList()) {
            $rules['laundry'] = ['required', 'array'];
        }

        $validated = $request->validate($rules);

        $booking = null;
        if ($allowsBookingLink && ! empty($validated['booking_id'])) {
            $booking = Booking::with('listing.property')->findOrFail($validated['booking_id']);
            abort_unless($booking->guest_id === Auth::id(), 403);
            abort_unless($service->isAvailableForProperty($booking->listing->property_id), 403, 'This service is not available at your booking\'s property.');
        }

        $details = $validated['details'] ?? [];

        if ($requiresBooking) {
            $details['delivery_address'] = $booking->deliveryAddressLabel();
        }
        $order = OrderBuilder::place($service, [
            'customer_id' => Auth::id(),
            'booking_id' => $booking?->id,
            'fulfillment_details' => $details,
            'scheduled_at' => $details['requested_time'] ?? $details['scheduled_time'] ?? null,
            'source' => 'web',
            'payment_method' => 'online',
        ], $request->only(['items', 'laundry']), $booking?->listing?->property);

        OrderNotifier::placed($order);

        return redirect()->route('service-orders.show', $order)
            ->with('status', 'Order placed! The provider will confirm shortly. You can pay now or after confirmation.');
    }

    public function index(): View
    {
        $orders = ServiceOrder::with(['service.category', 'service.media'])
            ->where('customer_id', Auth::id())
            ->latest()
            ->get();

        return view('services::orders', compact('orders'));
    }

    public function show(ServiceOrder $order, ServiceFulfillmentManager $handlers): View
    {
        $this->authorizeParticipant($order);

        $order->load(['service.provider', 'booking', 'items.options']);
        $handler = $handlers->driver($order->service->category->fulfillment_type);

        $user = Auth::user();
        $canReview = $order->customer_id === $user->id
            && $order->status === 'completed'
            && ! Review::where('reviewer_id', $user->id)
                ->where('reviewable_type', $order->service::class)
                ->where('reviewable_id', $order->service_id)
                ->exists();

        return view('services::order-show', compact('order', 'handler', 'canReview'));
    }

    public function cancel(ServiceOrder $order): RedirectResponse
    {
        $this->authorizeParticipant($order);

        abort_unless(in_array($order->status, ['pending', 'confirmed']), 422);

        $order->update(['status' => 'cancelled']);

        return back()->with('status', 'Order cancelled.');
    }

    private function authorizeParticipant(ServiceOrder $order): void
    {
        $user = Auth::user();

        abort_unless(
            $order->customer_id === $user->id || $order->service->provider_id === $user->id || $user->hasRole('Admin'),
            403
        );
    }
}
