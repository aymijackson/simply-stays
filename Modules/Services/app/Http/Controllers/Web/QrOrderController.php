<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Bookings\Models\Booking;
use Modules\Services\Fulfillment\ServiceFulfillmentManager;
use Modules\Services\Models\ServiceOrder;
use Modules\Services\Models\ServiceQrCode;
use Modules\Services\Support\OrderBuilder;
use Modules\Services\Support\OrderNotifier;
use Modules\Services\Support\PriceList;

/**
 * Ordering from a scanned QR code, with or without an account.
 *
 * Room codes know where to deliver. Generic codes ask for a location.
 * Either way the customer leaves a name and phone (email optional, for the
 * receipt) and follows the order from a private tracking link.
 */
class QrOrderController extends Controller
{
    public function show(Request $request, string $token, ServiceFulfillmentManager $handlers): View
    {
        $code = $this->code($token);

        if (! $request->session()->has("qr_scanned.{$code->id}")) {
            $code->increment('scans');
            $code->forceFill(['last_scanned_at' => now()])->save();
            $request->session()->put("qr_scanned.{$code->id}", true);
        }

        $service = $code->service;

        return view('services::qr.order', [
            'code' => $code,
            'service' => $service,
            'handler' => $handlers->driver($service->category->fulfillment_type),
            'priceList' => PriceList::for($service),
            'menuItems' => $service->category->fulfillment_type === 'food'
                ? $service->menuItems()->where('is_available', true)->with(['optionGroups.options', 'upsells', 'pairings'])->get()
                : collect(),
            'orderedBefore' => collect(),
            'property' => $code->location?->property,
            'needsLogin' => $this->needsLogin($code),
        ]);
    }

    public function store(Request $request, string $token, ServiceFulfillmentManager $handlers): RedirectResponse
    {
        $code = $this->code($token);
        $service = $code->service;
        $room = $code->location;
        $user = Auth::user();

        abort_if($this->needsLogin($code), 403, 'Please sign in to order this service.');

        $handler = $handlers->driver($service->category->fulfillment_type);
        $rules = [
            'contact_name' => ['required', 'string', 'max:100'],
            // Rooms can be reached on the intercom; everyone else needs a phone number.
            'contact_phone' => [$room ? 'nullable' : 'required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'payment_method' => ['required', Rule::in($room ? ['room_bill', 'on_delivery'] : ['on_delivery'])],
        ];
        foreach ($handler->validationRules() as $field => $fieldRules) {
            if ($room && $field === 'delivery_address') {
                continue;
            }
            $rules["details.{$field}"] = $fieldRules;
        }
        if (! $room) {
            $rules['details.delivery_address'] = ['required', 'string', 'max:255'];
        }
        if ($service->usesPriceList()) {
            $rules[$service->isLaundry() ? 'laundry' : 'items'] = ['required', 'array'];
        }

        $data = $request->validate($rules, [
            'contact_phone.regex' => 'Enter a valid phone number.',
            'details.delivery_address.required' => 'Tell us where to deliver or collect from.',
        ]);

        $details = $data['details'] ?? [];
        if ($room) {
            $details['delivery_address'] = $room->label();
        }

        $order = OrderBuilder::place($service, [
            'customer_id' => $user?->id,
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'booking_id' => $room ? $this->currentStay($room)?->id : null,
            'location_id' => $room?->id,
            'location_label' => $room?->label(),
            'fulfillment_details' => $details,
            'source' => $room ? 'qr_room' : 'qr',
            'payment_method' => $data['payment_method'],
        ], $request->only(['items', 'laundry']), $room?->property);

        OrderNotifier::placed($order);

        return redirect()->route('qr.track', $order->public_token)
            ->with('status', 'Order placed! We\'ll confirm it shortly.');
    }

    public function track(string $token): View
    {
        $order = ServiceOrder::where('public_token', $token)->with(['service.category', 'items.options'])->firstOrFail();

        return view('services::qr.track', compact('order'));
    }

    /** Polled by the tracking page so the status updates without a refresh. */
    public function status(string $token): JsonResponse
    {
        $order = ServiceOrder::where('public_token', $token)->firstOrFail(['id', 'status', 'updated_at']);

        return response()->json(['status' => $order->status, 'updated_at' => $order->updated_at?->toIso8601String()]);
    }

    private function code(string $token): ServiceQrCode
    {
        $code = ServiceQrCode::where('token', $token)->with(['service.category', 'service.properties', 'location.property'])->first();

        abort_unless($code && $code->is_active && $code->service?->status === 'published', 404);

        // A room code only works where the service is offered.
        if ($code->location) {
            abort_unless($code->service->isAvailableForProperty($code->location->property_id), 404);
        }

        return $code;
    }

    /** Guests-only services: a room code proves they're staying; a generic code needs a signed-in guest. */
    private function needsLogin(ServiceQrCode $code): bool
    {
        return $code->service->requiresBooking() && ! $code->location && ! Auth::check();
    }

    /** The stay currently running in this room's suite, so the order can go on that bill. */
    private function currentStay($room): ?Booking
    {
        if (! $room->listing_id) {
            return null;
        }

        return Booking::where('listing_id', $room->listing_id)
            ->where('status', 'confirmed')
            ->whereDate('check_in', '<=', today())
            ->whereDate('check_out', '>=', today())
            ->latest('check_in')
            ->first();
    }
}
