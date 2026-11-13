@extends(auth()->user()->portalLayout())

@section('title', 'New guest registration')

@section('content')
<div class="page page--tight">
    <div class="container" style="max-width: 900px;">
        <a href="{{ route('guest-forms.index') }}">&larr; Back to guest forms</a>

        {{-- Step 1: choose where the form comes from. A booking prefills everything it knows; a property alone gives the blank default form. --}}
        <div class="card" style="margin-top: 1rem;">
            <h1 style="margin-top: 0;">Guest registration form</h1>
            <p class="form-hint" style="margin-top: 0;">Pick a property for a blank form, or pick a booking to prefill guest, reservation and payment details.</p>

            <form method="GET" action="{{ route('guest-forms.create') }}" class="gform-picker">
                <div class="form-group">
                    <label class="form-label" for="pick-property">Property</label>
                    <select name="property" id="pick-property" class="form-control" onchange="this.form.booking && (this.form.booking.value = ''); this.form.submit();">
                        <option value="">Select a property&hellip;</option>
                        @foreach ($propertyOptions as $option)
                            <option value="{{ $option->getRouteKey() }}" @selected($property?->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($bookingOptions->isNotEmpty())
                    <div class="form-group">
                        <label class="form-label" for="pick-booking">Booking (optional)</label>
                        <select name="booking" id="pick-booking" class="form-control" onchange="this.form.property.value = ''; this.form.submit();">
                            <option value="">No booking &mdash; blank form</option>
                            @foreach ($bookingOptions as $option)
                                <option value="{{ $option->getRouteKey() }}" @selected($booking?->id === $option->id)>
                                    {{ $option->referenceCode() }} &middot; {{ $option->guest->name }} &middot; {{ $option->listing->title }} ({{ $option->check_in->format('M j') }}&ndash;{{ $option->check_out->format('M j') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <noscript><button type="submit" class="btn btn-outline">Load form</button></noscript>
            </form>
        </div>

        @if ($property)
            <form method="POST" action="{{ route('guest-forms.store') }}" class="card gform" style="margin-top: 1.5rem;">
                @csrf
                <input type="hidden" name="property_id" value="{{ $property->id }}">
                @if ($booking)
                    <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                    <div class="alert alert-success" style="margin-bottom: 1rem;">Prefilled from booking {{ $booking->referenceCode() }}. Check every field and correct anything that has changed.</div>
                @endif

                @include('bookings::guest-forms.partials.letterhead', ['property' => $property, 'settings' => $settings])
                @include('bookings::guest-forms.partials.fields', ['data' => $data, 'asStaff' => $asStaff])

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1.5rem;">Submit registration form</button>
            </form>
        @endif
    </div>
</div>
@endsection
