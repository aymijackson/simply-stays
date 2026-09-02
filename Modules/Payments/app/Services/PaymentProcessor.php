<?php

namespace Modules\Payments\Services;

use Illuminate\Support\Facades\DB;
use Modules\Payments\DataTransferObjects\VerifiedTransaction;
use Modules\Payments\Events\PaymentSucceeded;
use Modules\Payments\Models\PaymentTransaction;
use Modules\Payments\Notifications\PaymentReceiptNotification;
use Modules\Payments\Notifications\PaymentReceivedNotification;
use Modules\Payments\Support\PlatformAccount;

class PaymentProcessor
{
    public function __construct(private readonly WalletService $wallets) {}

    /**
     * Apply a gateway-verified result to our transaction record, idempotently.
     * Safe to call multiple times for the same event (webhook + user-return callback).
     */
    public function apply(PaymentTransaction $transaction, VerifiedTransaction $verified): PaymentTransaction
    {
        return DB::transaction(function () use ($transaction, $verified) {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'succeeded') {
                return $locked;
            }

            $locked->status = $verified->succeeded() ? 'succeeded' : ($verified->status === 'pending' ? 'pending' : 'failed');
            $locked->meta = array_merge($locked->meta ?? [], ['gateway_verification' => $verified->raw]);
            $locked->save();

            if ($locked->status === 'succeeded') {
                if ($locked->booking_id) {
                    $this->creditBookingHost($locked);
                } elseif ($locked->service_order_id) {
                    $this->creditServiceProvider($locked);
                } elseif ($locked->event_booking_id) {
                    $this->creditEventOrganizer($locked);
                }

                PaymentSucceeded::dispatch($locked);
            }

            return $locked;
        });
    }

    private function creditBookingHost(PaymentTransaction $transaction): void
    {
        $booking = $transaction->booking()->with(['listing.property.host', 'guest'])->first();
        $host = $booking?->listing?->property?->host;

        if ($host) {
            $this->wallets->credit(
                $host,
                $transaction->amount,
                $transaction->currency,
                $booking::class,
                $booking->id,
            );

            $host->notify(new PaymentReceivedNotification($transaction, $booking->listing->title));
        }

        $booking?->guest?->notify(new PaymentReceiptNotification($transaction, $booking->listing->title));
    }

    private function creditServiceProvider(PaymentTransaction $transaction): void
    {
        $order = $transaction->serviceOrder()->with(['service.provider', 'customer'])->first();

        if ($order?->service?->provider) {
            $split = CommissionCalculator::split($order->service, $transaction->amount);

            $this->wallets->credit(
                $order->service->provider,
                $split['providerShare'],
                $transaction->currency,
                $order::class,
                $order->id,
            );

            if ($split['platformFee'] > 0) {
                $this->wallets->credit(
                    PlatformAccount::user(),
                    $split['platformFee'],
                    $transaction->currency,
                    $order::class,
                    $order->id,
                );
            }

            $order->service->provider->notify(new PaymentReceivedNotification($transaction, $order->service->title));
        }

        $order?->customer?->notify(new PaymentReceiptNotification($transaction, $order->service->title));
    }

    private function creditEventOrganizer(PaymentTransaction $transaction): void
    {
        $booking = $transaction->eventBooking()->with(['occurrence.event.creator', 'guest'])->first();
        $event = $booking?->occurrence?->event;

        if ($event?->creator) {
            $split = CommissionCalculator::split($event, $transaction->amount);

            $this->wallets->credit(
                $event->creator,
                $split['providerShare'],
                $transaction->currency,
                $booking::class,
                $booking->id,
            );

            if ($split['platformFee'] > 0) {
                $this->wallets->credit(
                    PlatformAccount::user(),
                    $split['platformFee'],
                    $transaction->currency,
                    $booking::class,
                    $booking->id,
                );
            }

            $event->creator->notify(new PaymentReceivedNotification($transaction, $event->title));
        }

        $booking?->guest?->notify(new PaymentReceiptNotification($transaction, $event->title));
    }
}
