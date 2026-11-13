@extends('layouts.app')

@section('title', 'Order '.$order->referenceCode())

@section('content')
@php
    $steps = ['pending' => 'Received', 'confirmed' => 'Confirmed', 'in_progress' => 'In progress', 'completed' => 'Done'];
    $keys = array_keys($steps);
    $current = array_search($order->status, $keys, true);
@endphp
<div class="page page--tight">
    <div class="container qr-order">
        <div class="card qr-order__card">
            <p class="qr-order__eyebrow">Order {{ $order->referenceCode() }} · {{ $order->created_at->format('j M, g:i A') }}</p>
            <h1 class="qr-order__title">{{ $order->service->title }}</h1>
            @if ($order->deliverTo())
                <p class="qr-order__room"><x-icon name="map-pin" style="width:16px;height:16px;" /> {{ $order->deliverTo() }}</p>
            @endif

            @if ($order->status === 'cancelled')
                <x-alert type="danger">This order was cancelled. If that's unexpected, please call us.</x-alert>
            @else
                <ol class="qr-steps" data-track data-status-url="{{ route('qr.track.status', $order->public_token) }}" data-status="{{ $order->status }}">
                    @foreach ($steps as $key => $label)
                        @php $i = array_search($key, $keys, true); @endphp
                        <li @class(['is-done' => $current !== false && $i < $current, 'is-current' => $i === $current])>{{ $label }}</li>
                    @endforeach
                </ol>
                <p class="form-hint text-center" style="margin-top: -0.5rem;">This page updates by itself.</p>
            @endif

            @include('services::partials.receipt', ['order' => $order])

            <p class="text-muted" style="font-size: 0.88rem;">
                Ordered by {{ $order->contactName() }}@if ($order->contactPhone()) · {{ $order->contactPhone() }}@endif.
                @if ($order->contactEmail()) A receipt was sent to {{ $order->contactEmail() }}. @endif
                Bookmark this page to check on your order.
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var el = document.querySelector('[data-track]');
    if (!el || !window.fetch) { return; }
    var done = ['completed', 'cancelled'];
    function check() {
        if (document.hidden) { return setTimeout(check, 15000); }
        fetch(el.dataset.statusUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.status !== el.dataset.status) { location.reload(); return; }
                if (done.indexOf(d.status) === -1) { setTimeout(check, 15000); }
            })
            .catch(function () { setTimeout(check, 30000); });
    }
    if (done.indexOf(el.dataset.status) === -1) { setTimeout(check, 15000); }
})();
</script>
@endpush
