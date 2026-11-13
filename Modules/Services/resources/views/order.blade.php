@extends('layouts.app')

@section('title', 'Order ' . $service->title)

@section('content')
<div class="page page--tight">
    <div class="container" style="max-width: 720px;">
        <a href="{{ route('services.show', $service) }}">&larr; Back to service</a>

        <div class="card" style="margin-top: 1rem;">
            <h1 style="margin-bottom: 0.15rem;">Order: {{ $service->title }}</h1>
            @unless ($isFood || $service->usesPriceList())
                <p style="font-weight: 700; color: var(--color-navy-950); font-size: 1.1rem;">{{ $service->price()->format() }}</p>
                @include('services::partials.charges-note', ['service' => $service])
            @endunless

            @if ($errors->any())
                <x-alert type="danger">{{ $errors->first() }}</x-alert>
            @endif

            @if ($requiresBooking && $myBookings->isEmpty())
                <x-alert type="danger">This service is only available to guests with an active booking at this property. You don't have one yet.</x-alert>
            @else
            <form method="POST" action="{{ route('service-orders.store', $service) }}">
                @csrf

                @if ($isFood)
                    @include('services::partials.food-menu')
                @endif

                @if ($priceList && $service->usesPriceList())
                    @include('services::partials.laundry-picker', ['service' => $service, 'priceList' => $priceList])
                @endif

                @if ($allowsBookingLink && ($myBookings->isNotEmpty() || $requiresBooking))
                    <div class="form-group">
                        <label class="form-label" for="booking_id">{{ $requiresBooking ? 'Select your booking' : 'Link to a booking (optional)' }}</label>
                        <select name="booking_id" id="booking_id" class="form-control" @if ($requiresBooking) required @endif>
                            @unless ($requiresBooking)
                                <option value="">None</option>
                            @endunless
                            @foreach ($myBookings as $booking)
                                <option value="{{ $booking->id }}" @selected(old('booking_id') == $booking->id)>
                                    {{ $booking->listing->title }} ({{ $booking->check_in->format('M j') }} &ndash; {{ $booking->check_out->format('M j') }})
                                </option>
                            @endforeach
                        </select>
                        @if ($requiresBooking)
                            <p class="form-hint">This service delivers to your booking's address automatically.</p>
                        @endif
                    </div>
                @endif

                @foreach ($handler->fields() as $field)
                    @continue($requiresBooking && $field['name'] === 'delivery_address')
                    <div class="form-group">
                        <label class="form-label" for="details_{{ $field['name'] }}">{{ $field['label'] }}</label>

                        @if ($field['type'] === 'textarea')
                            <textarea name="details[{{ $field['name'] }}]" id="details_{{ $field['name'] }}" class="form-control" rows="3">{{ old("details.{$field['name']}") }}</textarea>
                        @else
                            <input
                                type="{{ $field['type'] }}"
                                name="details[{{ $field['name'] }}]"
                                id="details_{{ $field['name'] }}"
                                class="form-control"
                                value="{{ old("details.{$field['name']}") }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                @if ($field['required']) required @endif
                            >
                        @endif

                        @if ($field['name'] === 'delivery_address' && $myBookings->isNotEmpty())
                            <p class="form-hint">
                                Use an address from a booking:
                                @foreach ($myBookings as $booking)
                                    <button type="button" class="btn btn-outline btn-sm" data-fill-address="{{ $booking->deliveryAddressLabel() }}">{{ $booking->listing->title }}</button>
                                @endforeach
                            </p>
                        @endif

                        @error("details.{$field['name']}")
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary btn-block">Place order</button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
