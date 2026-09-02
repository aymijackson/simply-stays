<?php

namespace Modules\Payments\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\Payments\Gateways\PaymentGatewayManager;
use Modules\Payments\Models\PaymentTransaction;
use Modules\Payments\Models\PaymentWebhookLog;
use Modules\Payments\Services\PaymentProcessor;

class WebhookController extends Controller
{
    public function handle(Request $request, string $gateway, PaymentGatewayManager $gateways, PaymentProcessor $processor): JsonResponse
    {
        $log = PaymentWebhookLog::create([
            'gateway' => $gateway,
            'event_type' => $request->input('event', $request->input('type', 'unknown')),
            'payload' => $request->all(),
        ]);

        try {
            $driver = $gateways->driver($gateway);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Unknown gateway.'], 404);
        }

        if (! $driver->verifyWebhookSignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $reference = $driver->referenceFromWebhook($request);

        if (! $reference) {
            return response()->json(['message' => 'No transaction reference in payload.'], 422);
        }

        $transaction = PaymentTransaction::where('gateway_reference', $reference)
            ->where('gateway', $gateway)
            ->first();

        if (! $transaction) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        $verified = $driver->verifyTransaction($transaction->gateway_reference);
        $processor->apply($transaction, $verified);

        $log->update(['processed_at' => now()]);

        return response()->json(['message' => 'ok']);
    }
}
