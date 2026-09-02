@extends('layouts.app')

@section('title', 'Payments & earnings')

@section('content')
<div class="page page--tight">
    <div class="container">
        <h1>Payments &amp; earnings</h1>

        @if ($wallets->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <span class="quick-link__icon" style="margin: 0 auto 1rem;"><x-icon name="wallet" /></span>
                <p style="margin: 0;">No wallet activity yet. Earnings from paid bookings, service orders, or referral commissions will show up here.</p>
            </div>
        @else
            <div class="module-grid" style="margin-bottom: 2rem;">
                @foreach ($wallets as $wallet)
                    <div class="card stat-tile">
                        <p>Balance ({{ $wallet->currency }})</p>
                        <h2>{{ \Modules\Core\Support\Money::fromMinorUnits($wallet->balance, \Modules\Core\Enums\Currency::from($wallet->currency))->format() }}</h2>
                    </div>
                @endforeach
            </div>

            @foreach ($wallets as $wallet)
                @if ($wallet->transactions->isNotEmpty())
                    <div class="card" style="margin-bottom: 1.5rem; overflow-x: auto;">
                        <h2>Recent activity &mdash; {{ $wallet->currency }}</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Balance after</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($wallet->transactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction->created_at->format('M j, Y') }}</td>
                                        <td><span class="badge badge--{{ $transaction->type === 'credit' ? 'success' : 'danger' }}">{{ ucfirst($transaction->type) }}</span></td>
                                        <td>{{ $transaction->type === 'credit' ? '+' : '-' }}{{ \Modules\Core\Support\Money::fromMinorUnits($transaction->amount, \Modules\Core\Enums\Currency::from($wallet->currency))->format() }}</td>
                                        <td>{{ \Modules\Core\Support\Money::fromMinorUnits($transaction->balance_after, \Modules\Core\Enums\Currency::from($wallet->currency))->format() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach
        @endif
    </div>
</div>
@endsection
