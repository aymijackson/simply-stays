{{-- Header block mirroring the printed form: property name + address on the left, contact details on the right. --}}
<div class="gform-letterhead">
    <div class="gform-letterhead__brand">
        <span class="gform-letterhead__mark">{{ strtoupper(substr($property->name, 0, 1)) }}</span>
        <div>
            <div class="gform-letterhead__name">{{ $property->name }}</div>
            <div class="gform-letterhead__tagline">{{ ucfirst($property->property_type) }} &middot; {{ $property->city }}</div>
        </div>
    </div>
    <div class="gform-letterhead__contact">
        <div>{{ $property->address }}</div>
        <div>{{ $property->city }}, {{ $property->country }}</div>
        @if ($settings->contact_phone || $settings->contact_phone_2)
            <div>Tel: {{ collect([$settings->contact_phone, $settings->contact_phone_2])->filter()->implode(' | ') }}</div>
        @endif
        @if ($settings->contact_email)
            <div>{{ $settings->contact_email }}</div>
        @endif
    </div>
</div>

<div class="gform-title">
    <h2>{{ \Modules\Bookings\Support\GuestRegistrationForm::TITLE }}</h2>
    <p>{{ \Modules\Bookings\Support\GuestRegistrationForm::INTRO }}</p>
</div>
