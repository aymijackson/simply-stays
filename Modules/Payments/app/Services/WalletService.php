<?php

namespace Modules\Payments\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Payments\Models\Wallet;
use Modules\Payments\Models\WalletTransaction;

class WalletService
{
    public function credit(Model $holder, int $amountMinorUnits, string $currency, string $referenceType, int $referenceId): WalletTransaction
    {
        return $this->applyEntry($holder, $amountMinorUnits, $currency, $referenceType, $referenceId, 'credit');
    }

    public function debit(Model $holder, int $amountMinorUnits, string $currency, string $referenceType, int $referenceId): WalletTransaction
    {
        return $this->applyEntry($holder, $amountMinorUnits, $currency, $referenceType, $referenceId, 'debit');
    }

    private function applyEntry(Model $holder, int $amountMinorUnits, string $currency, string $referenceType, int $referenceId, string $type): WalletTransaction
    {
        return DB::transaction(function () use ($holder, $amountMinorUnits, $currency, $referenceType, $referenceId, $type) {
            $wallet = Wallet::query()
                ->where('holder_type', $holder->getMorphClass())
                ->where('holder_id', $holder->getKey())
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'holder_type' => $holder->getMorphClass(),
                    'holder_id' => $holder->getKey(),
                    'balance' => 0,
                    'currency' => $currency,
                ]);
            }

            $type === 'credit'
                ? $wallet->increment('balance', $amountMinorUnits)
                : $wallet->decrement('balance', $amountMinorUnits);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => $type,
                'amount' => $amountMinorUnits,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_after' => $wallet->fresh()->balance,
            ]);
        });
    }
}
