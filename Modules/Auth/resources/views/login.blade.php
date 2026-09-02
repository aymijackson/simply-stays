@extends('layouts.app')

@section('title', 'Log in')

@section('content')
<div class="auth-split">
    <div class="auth-split__brand">
        <h2>Welcome back to Short Stay</h2>
        <p>Log in to manage your bookings, listings, service orders, or referrals &mdash; all from one dashboard.</p>

        <div class="auth-split__points">
            <div><span><x-icon name="shield-check" /></span> Secure, gateway-verified payments</div>
            <div><span><x-icon name="clock" /></span> Track every booking in real time</div>
            <div><span><x-icon name="headset" /></span> Hosts &amp; providers confirm directly</div>
        </div>
    </div>

    <div class="auth-split__form">
        <div class="card">
            <h1>Log in</h1>
            <p>Enter your details to continue.</p>

            @if ($errors->any())
                <x-alert type="danger">{{ $errors->first() }}</x-alert>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <x-input name="email" type="email" label="Email address" required autofocus />
                <x-input name="password" type="password" label="Password" required />

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.4rem; font-weight: 500; cursor: pointer;">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Log in</button>
            </form>

            <p style="margin-top: 1.25rem; text-align: center;">
                Don't have an account? <a href="{{ route('register') }}">Sign up</a>
            </p>
        </div>
    </div>
</div>
@endsection
