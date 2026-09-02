@extends('layouts.app')

@section('title', 'Pay now')

@section('content')
<div class="page page--tight">
    <div class="container" style="max-width: 480px;">
        <div class="card">
            <span class="quick-link__icon" style="margin-bottom: 1rem;"><x-icon name="credit-card" /></span>
            <h1>Choose how to pay</h1>
            <div style="background: var(--color-bg-alt); border-radius: var(--radius-sm); padding: 1rem; margin-bottom: 1.5rem;">
                <p style="margin-bottom: 0.15rem; color: var(--color-navy-950); font-weight: 700;">{{ $payable->paymentDescription() }}</p>
                <p style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--color-navy-950);">{{ \Modules\Core\Support\Money::fromMinorUnits($payable->paymentAmountMinorUnits(), \Modules\Core\Enums\Currency::from($payable->paymentCurrency()))->format() }}</p>
            </div>

            @if ($errors->any())
                <x-alert type="danger">{{ $errors->first() }}</x-alert>
            @endif

            <form method="POST" action="{{ $initiateRoute }}">
                @csrf

                <div class="form-group">
                    <label class="radio-card" style="display: flex; align-items: center; margin-bottom: 0.6rem;">
                        <input type="radio" name="gateway" value="stripe" checked style="margin-right: 0.6rem;">
                        <x-icon name="credit-card" style="width:18px;height:18px;margin-right:0.5rem;color:var(--color-primary);" />
                        <span>Stripe <span class="text-muted" style="font-size:0.85rem;">&mdash; card, global</span></span>
                    </label>
                    <label class="radio-card" style="display: flex; align-items: center; margin-bottom: 0.6rem;">
                        <input type="radio" name="gateway" value="paystack" style="margin-right: 0.6rem;">
                        <x-icon name="wallet" style="width:18px;height:18px;margin-right:0.5rem;color:var(--color-primary);" />
                        <span>Paystack <span class="text-muted" style="font-size:0.85rem;">&mdash; card, bank, mobile money</span></span>
                    </label>
                    <label class="radio-card" style="display: flex; align-items: center;">
                        <input type="radio" name="gateway" value="flutterwave" style="margin-right: 0.6rem;">
                        <x-icon name="globe" style="width:18px;height:18px;margin-right:0.5rem;color:var(--color-primary);" />
                        <span>Flutterwave <span class="text-muted" style="font-size:0.85rem;">&mdash; card, bank, mobile money</span></span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Continue to payment</button>
            </form>

            <p class="form-hint text-center" style="margin-top: 1rem;"><x-icon name="shield-check" style="width:13px;height:13px;vertical-align:-2px;" /> Payments are processed securely by your chosen provider.</p>
        </div>
    </div>
</div>
@endsection
