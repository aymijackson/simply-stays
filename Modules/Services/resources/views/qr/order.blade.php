@extends('layouts.app')

@section('title', 'Order '.$service->title)

@section('content')
@php
    $room = $code->location;
    $user = auth()->user();
    $isFood = $service->category->fulfillment_type === 'food';
@endphp
<div class="page page--tight">
    <div class="container qr-order">
        <div class="card qr-order__card">
            @if ($service->coverPhotoUrl())
                <img src="{{ $service->coverPhotoUrl() }}" alt="{{ $service->title }}" class="qr-order__cover">
            @endif
            <p class="qr-order__eyebrow">{{ $room ? $room->property->name : 'Order' }}</p>
            <h1 class="qr-order__title">{{ $service->title }}</h1>
            @if ($room)
                <p class="qr-order__room"><x-icon name="map-pin" style="width:16px;height:16px;" /> <span>Delivering to <strong>{{ $room->label() }}</strong></span></p>
            @endif
            @if ($service->description)
                <p class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($service->description), 220) }}</p>
            @endif

            @if ($errors->any())
                <x-alert type="danger">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</x-alert>
            @endif

            @if ($needsLogin)
                <x-alert type="danger">This service is for staying guests. Please sign in to order, or scan the code in your room.</x-alert>
                <a href="{{ route('login') }}" class="btn btn-primary btn-block">Sign in</a>
            @else
                <form method="POST" action="{{ route('qr.order.store', $code->token) }}">
                    @csrf

                    @if ($isFood)
                        @include('services::partials.food-menu')
                        @include('services::partials.charges-note', ['service' => $service, 'property' => $property])
                    @elseif ($priceList && $service->usesPriceList())
                        @include('services::partials.laundry-picker', ['service' => $service, 'priceList' => $priceList, 'property' => $property])
                    @else
                        <p class="qr-order__price">{{ $service->price()->format() }}</p>
                        @include('services::partials.charges-note', ['service' => $service, 'property' => $property])
                    @endif

                    <fieldset class="ops-fieldset">
                        <legend>Your details</legend>
                        <x-input name="contact_name" label="Name" :value="$user?->name" required maxlength="100" autocomplete="name" />
                        <div class="form-row">
                            <x-input name="contact_phone" :label="$room ? 'Phone (optional)' : 'Phone'" type="tel" :value="$user?->phone" :required="! $room" maxlength="30" autocomplete="tel"
                                     :hint="$room ? 'We\'ll call your room on the intercom to confirm.' : 'We\'ll call or text to confirm your order.'" />
                            <x-input name="contact_email" label="Email (optional)" type="email" :value="$user?->email" maxlength="190" autocomplete="email" hint="For your receipt and order updates." />
                        </div>
                    </fieldset>

                    <fieldset class="ops-fieldset">
                        <legend>{{ $room ? 'Details' : 'Where and when' }}</legend>
                        @unless ($room)
                            <x-input name="details[delivery_address]" label="Where should we deliver or collect?" :value="old('details.delivery_address')" required maxlength="255"
                                     placeholder="e.g. Poolside table 4, or your full address" />
                        @endunless
                        @foreach ($handler->fields() as $field)
                            @continue($field['name'] === 'delivery_address')
                            <div class="form-group">
                                <label class="form-label" for="details_{{ $field['name'] }}">{{ $field['label'] }}@unless ($field['required']) <span class="text-muted">(optional)</span>@endunless</label>
                                @if ($field['type'] === 'textarea')
                                    <textarea name="details[{{ $field['name'] }}]" id="details_{{ $field['name'] }}" class="form-control" rows="2">{{ old("details.{$field['name']}") }}</textarea>
                                @else
                                    <input type="{{ $field['type'] }}" name="details[{{ $field['name'] }}]" id="details_{{ $field['name'] }}" class="form-control" value="{{ old("details.{$field['name']}") }}" placeholder="{{ $field['placeholder'] ?? '' }}" @if ($field['required']) required @endif>
                                @endif
                                @error("details.{$field['name']}")<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </fieldset>

                    <fieldset class="ops-fieldset">
                        <legend>Payment</legend>
                        @if ($room)
                            <label class="ops-check"><input type="radio" name="payment_method" value="room_bill" @checked(old('payment_method', 'room_bill') === 'room_bill')> Add to my room bill</label>
                        @endif
                        <label class="ops-check"><input type="radio" name="payment_method" value="on_delivery" @checked(old('payment_method', $room ? 'room_bill' : 'on_delivery') === 'on_delivery')> Pay on delivery (cash, POS or transfer)</label>
                    </fieldset>

                    <button type="submit" class="btn btn-primary btn-block qr-order__submit">Place order</button>
                    @guest
                        <p class="form-hint text-center">No account needed. Have one? <a href="{{ route('login') }}">Sign in</a> to keep your orders together.</p>
                    @endguest
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
