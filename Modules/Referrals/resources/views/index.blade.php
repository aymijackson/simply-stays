@extends('layouts.app')

@section('title', 'Referrals')

@section('content')
<div class="page page--tight">
    <div class="container">
        <h1>My referral program</h1>

        <div class="dashboard-hero">
            <span class="badge">Your code</span>
            <h1 style="font-size: 2.1rem;">{{ $agent->referral_code }}</h1>

            <label class="form-label" style="color: rgba(255,255,255,0.85); margin-top: 1rem;">Share this signup link</label>
            <input type="text" class="form-control" readonly value="{{ route('register', ['ref' => $agent->referral_code]) }}" onclick="this.select()" style="background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.25); color: #fff;">

            <p style="margin-top: 0.85rem; margin-bottom: 0; color: rgba(255,255,255,0.85);">
                You earn {{ rtrim(rtrim(number_format($agent->commission_rate, 2), '0'), '.') }}% commission on bookings made by guests you refer.
            </p>
        </div>

        <div class="module-grid" style="margin-bottom: 1.5rem;">
            @forelse ($wallets as $wallet)
                <div class="card stat-tile">
                    <p>Wallet balance ({{ $wallet->currency }})</p>
                    <h2>{{ \Modules\Core\Support\Money::fromMinorUnits($wallet->balance, \Modules\Core\Enums\Currency::from($wallet->currency))->format() }}</h2>
                </div>
            @empty
                <div class="card">
                    <p style="margin: 0;">No commission earned yet. Share your link to get started.</p>
                </div>
            @endforelse
        </div>

        <h2>Your referrals</h2>

        @if ($referrals->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin: 0;">No one has booked using your code yet.</p>
            </div>
        @else
            <div class="entity-grid">
                @foreach ($referrals as $referral)
                    <div class="card">
                        <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                            <span class="avatar">{{ strtoupper(substr($referral->referredUser->name ?? 'G', 0, 1)) }}</span>
                            <div>
                                <div class="entity-card__title" style="margin: 0;">{{ $referral->referredUser->name ?? 'Guest' }}</div>
                                <div class="entity-card__meta" style="margin: 0;">{{ class_basename($referral->referable_type) }} #{{ $referral->referable_id }}</div>
                            </div>
                        </div>
                        <p style="font-weight: 800; color: var(--color-navy-950); margin-bottom: 0.5rem;">
                            {{ \Modules\Core\Support\Money::fromMinorUnits($referral->commission_amount, \Modules\Core\Enums\Currency::from($referral->currency))->format() }}
                        </p>
                        <x-status-badge :status="$referral->status" />
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
