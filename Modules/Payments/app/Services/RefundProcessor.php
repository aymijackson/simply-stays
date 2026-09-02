<?php

namespace Modules\Payments\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Payments\Events\PaymentRefunded;
use Modules\Payments\Exceptions\PaymentException;
use Modules\Payments\Gateways\PaymentGatewayManager;
use Modules\Payments\Models\PaymentTransaction;
use Modules\Payments\Support\PlatformAccount;

class RefundProcessor
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly WalletService $wallets,
    ) {}

    public function refund(PaymentTransaction $charge): PaymentTransaction
    {
        if ($charge->type !== 'charge' || $charge->status !== 'succeeded') {
            throw new PaymentException('Only a succeeded charge can be refunded.');
        }

        $driver = $this->gateways->driver($charge->gateway);

        if (! $driver->refund($charge->gateway_reference, $charge->amount)) {
            throw new PaymentException('The refund was rejected by the payment gateway.');
        }

        return DB::transaction(function () use ($charge) {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::whereKey($charge->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'refunded') {
                return $locked;
            }

            $locked->update(['status' => 'refunded']);

            $refundTransaction = PaymentTransaction::create([
                'booking_id' => $locked->booking_id,
                'service_order_id' => $locked->service_order_id,
                'event_booking_id' => $locked->event_booking_id,
                'payer_id' => $locked->payer_id,
                'gateway' => $locked->gateway,
                'reference' => (string) Str::uuid(),
                'gateway_reference' => $locked->gateway_reference,
                'type' => 'refund',
                'amount' => $locked->amount,
                'currency' => $locked->currency,
                'status' => 'succeeded',
            ]);

            if ($locked->booking_id) {
                $this->reverseBookingHostCredit($locked);
            } elseif ($locked->service_order_id) {
                $this->reverseServiceProviderCredit($locked);
            } elseif ($locked->event_booking_id) {
                $this->reverseEventOrganizerCredit($locked);
            }

            PaymentRefunded::dispatch($locked);

            return $refundTransaction;
        });
    }

    private function reverseBookingHostCredit(PaymentTransaction $transaction): void
    {
        $booking = $transaction->booking()->with('listing.property.host')->first();
        $host = $booking?->listing?->property?->host;

        if ($host) {
            $this->wallets->debit(
                $host,
                $transaction->amount,
                $transaction->currency,
                $booking::class,
                $booking->id,
            );
        }

        $booking?->update(['status' => 'cancelled']);
    }

    private function reverseServiceProviderCredit(PaymentTransaction $transaction): void
    {
        $order = $transaction->serviceOrder()->with('service.provider')->first();

        if ($order?->service?->provider) {
            $split = CommissionCalculator::split($order->service, $transaction->amount);

            $this->wallets->debit(
                $order->service->provider,
                $split['providerShare'],
                $transaction->currency,
                $order::class,
                $order->id,
            );

            if ($split['platformFee'] > 0) {
                $this->wallets->debit(
                    PlatformAccount::user(),
                    $split['platformFee'],
                    $transaction->currency,
                    $order::class,
                    $order->id,
                );
            }
        }

        $order?->update(['status' => 'cancelled']);
    }

    private function reverseEventOrganizerCredit(PaymentTransaction $transaction): void
    {
        $booking = $transaction->eventBooking()->with('occurrence.event.creator')->first();
        $event = $booking?->occurrence?->event;

        if ($event?->creator) {
            $split = CommissionCalculator::split($event, $transaction->amount);

            $this->wallets->debit(
                $event->creator,
                $split['providerShare'],
                $transaction->currency,
                $booking::class,
                $booking->id,
            );

            if ($split['platformFee'] > 0) {
                $this->wallets->debit(
                    PlatformAccount::user(),
                    $split['platformFee'],
                    $transaction->currency,
                    $booking::class,
                    $booking->id,
                );
            }
        }

        $booking?->update(['status' => 'cancelled']);
    }
}
