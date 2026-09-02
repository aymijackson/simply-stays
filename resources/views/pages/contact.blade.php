@extends('layouts.app')

@section('title', 'Contact us')

@section('content')
<div class="page">
    <div class="container" style="max-width: 640px;">
        <h1>Contact us</h1>
        <p>Have a question about a booking, a listing, or your account? Reach out and we'll get back to you.</p>

        <div class="card">
            @if ($settings->contact_email)
                <div class="trust-item" style="margin-bottom: 1.25rem;">
                    <span class="trust-item__icon"><x-icon name="mail" style="width:18px;height:18px;" /></span>
                    <div>
                        <h3>Email</h3>
                        <p><a href="mailto:{{ $settings->contact_email }}">{{ $settings->contact_email }}</a></p>
                    </div>
                </div>
            @endif

            @if ($settings->contact_phone)
                <div class="trust-item" style="margin-bottom: 1.25rem;">
                    <span class="trust-item__icon"><x-icon name="phone" style="width:18px;height:18px;" /></span>
                    <div>
                        <h3>Phone</h3>
                        <p><a href="tel:{{ $settings->contact_phone }}">{{ $settings->contact_phone }}</a></p>
                    </div>
                </div>
            @endif

            @if ($settings->contact_address)
                <div class="trust-item">
                    <span class="trust-item__icon"><x-icon name="map-pin" style="width:18px;height:18px;" /></span>
                    <div>
                        <h3>Address</h3>
                        <p>{{ $settings->contact_address }}</p>
                    </div>
                </div>
            @endif

            @unless ($settings->contact_email || $settings->contact_phone || $settings->contact_address)
                <p>Contact details haven't been added yet.</p>
            @endunless
        </div>
    </div>
</div>
@endsection
