@extends('layouts.app')

@section('title', 'Sign up')

@section('content')
<div class="auth-split">
    <div class="auth-split__brand">
        <h2>Join Short Stay</h2>
        <p>Book your next stay, list a property, offer a service, or refer travelers &mdash; one account does it all.</p>

        <div class="auth-split__points">
            <div><span><x-icon name="home" /></span> Thousands of stays, ready to book</div>
            <div><span><x-icon name="sparkles" /></span> Laundry &amp; transport, sorted alongside your stay</div>
            <div><span><x-icon name="wallet" /></span> Hosts &amp; providers get paid out fast</div>
        </div>
    </div>

    <div class="auth-split__form">
        <div class="card">
            <h1>Create your account</h1>
            <p>Book stays or list your own property in minutes.</p>

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label">I want to</label>
                    <div class="radio-card-group">
                        <label class="radio-card">
                            <input type="radio" name="account_type" value="guest" checked>
                            Book stays
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="account_type" value="host">
                            List a property
                        </label>
                    </div>
                    @error('account_type')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <x-input name="name" label="Full name" required autofocus />
                <x-input name="email" type="email" label="Email address" required />
                <x-input name="phone" type="tel" label="Phone number" hint="Optional, used for booking updates" />
                <x-input name="password" type="password" label="Password" required />
                <x-input name="password_confirmation" type="password" label="Confirm password" required />
                <x-input name="referral_code" label="Referral code" hint="Optional &mdash; got a code from an agent?" value="{{ old('referral_code', $referralCode ?? null) }}" />

                <button type="submit" class="btn btn-primary btn-block">Create account</button>
            </form>

            <p style="margin-top: 1.25rem; text-align: center;">
                Already have an account? <a href="{{ route('login') }}">Log in</a>
            </p>
        </div>
    </div>
</div>
@endsection
