<?php

namespace Modules\Payments\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Bookings\Models\Booking;
use Modules\Events\Models\EventBooking;
use Modules\Payments\Contracts\Payable;
use Modules\Payments\DataTransferObjects\PaymentInitiationData;
use Modules\Payments\Exceptions\PaymentException;
use Modules\Payments\Gateways\PaymentGatewayManager;
use Modules\Payments\Models\PaymentTransaction;
use Modules\Payments\Services\PaymentProcessor;
use Modules\Services\Models\ServiceOrder;

class CheckoutController extends Controller
{
    public function chooseForBooking(Booking $booking): View
    {
        $this->authorizePayer($booking);
        abort_if($this->alreadyPaid('booking_id', $booking->id), 422, 'This booking has already been paid for.');

        return view('payments::choose', [
            'payable' => $booking,
            'initiateRoute' => route('checkout.initiate', $booking),
        ]);
    }

    public function initiateForBooking(Request $request, Booking $booking, PaymentGatewayManager $gateways): RedirectResponse
    {
        $this->authorizePayer($booking);
        abort_if($this->alreadyPaid('booking_id', $booking->id), 422, 'This booking has already been paid for.');

        return $this->initiate($request, $booking, $gateways, ['booking_id' => $booking->id]);
    }

    public function chooseForServiceOrder(ServiceOrder $serviceOrder): View
    {
        $this->authorizePayer($serviceOrder);
        abort_if($this->alreadyPaid('service_order_id', $serviceOrder->id), 422, 'This order has already been paid for.');

        return view('payments::choose', [
            'payable' => $serviceOrder,
            'initiateRoute' => route('checkout.initiate.service', $serviceOrder),
        ]);
    }

    public function initiateForServiceOrder(Request $request, ServiceOrder $serviceOrder, PaymentGatewayManager $gateways): RedirectResponse
    {
        $this->authorizePayer($serviceOrder);
        abort_if($this->alreadyPaid('service_order_id', $serviceOrder->id), 422, 'This order has already been paid for.');

        return $this->initiate($request, $serviceOrder, $gateways, ['service_order_id' => $serviceOrder->id]);
    }

    public function chooseForEventBooking(EventBooking $eventBooking): View
    {
        $this->authorizePayer($eventBooking);
        abort_if($this->alreadyPaid('event_booking_id', $eventBooking->id), 422, 'This booking has already been paid for.');

        return view('payments::choose', [
            'payable' => $eventBooking,
            'initiateRoute' => route('checkout.initiate.event', $eventBooking),
        ]);
    }

    public function initiateForEventBooking(Request $request, EventBooking $eventBooking, PaymentGatewayManager $gateways): RedirectResponse
    {
        $this->authorizePayer($eventBooking);
        abort_if($this->alreadyPaid('event_booking_id', $eventBooking->id), 422, 'This booking has already been paid for.');

        return $this->initiate($request, $eventBooking, $gateways, ['event_booking_id' => $eventBooking->id]);
    }

    public function callback(Request $request, string $gateway, PaymentGatewayManager $gateways, PaymentProcessor $processor): RedirectResponse
    {
        $transaction = PaymentTransaction::where('reference', $request->query('reference'))
            ->where('gateway', $gateway)
            ->firstOrFail();

        abort_unless($transaction->payer_id === Auth::id(), 403);

        $returnUrl = match (true) {
            (bool) $transaction->booking_id => route('bookings.show', $transaction->booking_id),
            (bool) $transaction->service_order_id => route('service-orders.show', $transaction->service_order_id),
            default => route('event-bookings.show', $transaction->event_booking_id),
        };

        if ($request->boolean('cancelled')) {
            if ($transaction->status === 'pending') {
                $transaction->update(['status' => 'failed']);
            }

            return redirect($returnUrl)->with('status', 'Payment was cancelled.');
        }

        $driver = $gateways->driver($gateway);
        $verified = $driver->verifyTransaction($transaction->gateway_reference);
        $transaction = $processor->apply($transaction, $verified);

        $message = $transaction->status === 'succeeded'
            ? 'Payment successful!'
            : 'Payment could not be confirmed yet. If you completed payment, this will update shortly.';

        return redirect($returnUrl)->with('status', $message);
    }

    /**
     * @param  Model&Payable  $payable
     */
    private function initiate(Request $request, Model&Payable $payable, PaymentGatewayManager $gateways, array $linkColumns): RedirectResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', 'in:stripe,paystack,flutterwave'],
        ]);

        $reference = (string) Str::uuid();

        $transaction = PaymentTransaction::create([
            ...$linkColumns,
            'payer_id' => $payable->payerId(),
            'gateway' => $validated['gateway'],
            'reference' => $reference,
            'type' => 'charge',
            'amount' => $payable->paymentAmountMinorUnits(),
            'currency' => $payable->paymentCurrency(),
            'status' => 'pending',
        ]);

        $driver = $gateways->driver($validated['gateway']);

        try {
            $result = $driver->initialize(new PaymentInitiationData(
                reference: $reference,
                amountMinorUnits: $payable->paymentAmountMinorUnits(),
                currency: $payable->paymentCurrency(),
                description: $payable->paymentDescription(),
                callbackUrl: route('payments.callback', ['gateway' => $driver->name()]),
                customerEmail: Auth::user()->email,
                metadata: [...$linkColumns, 'reference' => $reference],
            ));
        } catch (PaymentException $exception) {
            $transaction->update([
                'status' => 'failed',
                'meta' => ['error' => $exception->getMessage()],
            ]);

            return back()->withErrors([
                'gateway' => 'We could not start the payment with this provider. Please try again or choose a different method.',
            ]);
        }

        $transaction->update(['gateway_reference' => $result->gatewayReference]);

        return redirect()->away($result->redirectUrl);
    }

    /**
     * @param  Model&Payable  $payable
     */
    private function authorizePayer(Model&Payable $payable): void
    {
        abort_unless($payable->payerId() === Auth::id(), 403);
    }

    private function alreadyPaid(string $column, int $id): bool
    {
        return PaymentTransaction::where($column, $id)
            ->where('type', 'charge')
            ->where('status', 'succeeded')
            ->exists();
    }
}
