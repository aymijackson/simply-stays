@extends(auth()->user()->portalLayout())

@section('title', $isAdmin ? 'Service orders' : 'Incoming orders')

@php
    $nextSteps = [
        'pending' => ['confirmed' => 'Confirm', 'cancelled' => 'Decline'],
        'confirmed' => ['in_progress' => 'Start', 'cancelled' => 'Cancel'],
        'in_progress' => ['completed' => 'Mark complete', 'cancelled' => 'Cancel'],
    ];
    $sources = ['qr_room' => 'Room QR', 'qr' => 'QR code', 'web' => 'Website'];
@endphp

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('provider.services.index') }}">&larr; Back to {{ $isAdmin ? 'all services' : 'my services' }}</a>

        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin: 1rem 0;">
            <h1 style="margin: 0;">{{ $isAdmin ? 'Service orders' : 'Incoming orders' }}</h1>
            <span class="text-muted" data-orders-live>New orders appear here automatically.</span>
        </div>

        <div class="ops-subnav">
            <a href="{{ route('provider.services.orders') }}" @class(['is-active' => $view === 'open'])>Open ({{ $openCount }})</a>
            <a href="{{ route('provider.services.orders', ['view' => 'done']) }}" @class(['is-active' => $view === 'done'])>Completed &amp; cancelled</a>
            <a href="{{ route('provider.services.orders', ['view' => 'all']) }}" @class(['is-active' => $view === 'all'])>All</a>
        </div>

        @if ($orders->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin: 0;">{{ $view === 'open' ? 'No open orders right now.' : 'No orders yet.' }}</p>
            </div>
        @else
            <div class="so-list">
                @foreach ($orders as $order)
                    @php $isNew = $newIds->contains($order->id); @endphp
                    <article class="card so-card @if ($isNew) is-new @endif" id="order-{{ $order->id }}">
                        <header class="so-card__head">
                            <div>
                                <strong>#{{ $order->id }} · {{ $order->service->title }}</strong>
                                @if ($isNew)<span class="badge badge--gold">New</span>@endif
                                <div class="form-hint" style="margin: 0;">
                                    {{ $order->created_at->diffForHumans() }} · {{ $sources[$order->source] ?? 'Website' }}
                                    @if ($isAdmin) · {{ $order->service->providerLabel() }} @endif
                                </div>
                            </div>
                            <x-status-badge :status="$order->status" />
                        </header>

                        <div class="so-card__body">
                            <div class="so-card__who">
                                @if ($order->deliverTo())
                                    <div class="so-card__where"><x-icon name="map-pin" style="width:15px;height:15px;" /> {{ $order->deliverTo() }}</div>
                                @endif
                                <div><strong>{{ $order->contactName() }}</strong> @unless ($order->customer_id)<span class="text-muted">(no account)</span>@endunless</div>
                                @if ($order->contactPhone())<div><a href="tel:{{ preg_replace('/[^0-9+]/', '', $order->contactPhone()) }}">{{ $order->contactPhone() }}</a></div>@endif
                                @if ($order->contactEmail())<div class="text-muted">{{ $order->contactEmail() }}</div>@endif
                                @if (! empty($order->fulfillment_details['instructions']))<div class="so-card__note">“{{ $order->fulfillment_details['instructions'] }}”</div>@endif
                            </div>

                            <div class="so-card__items">
                                @foreach ($order->items as $item)
                                    <div class="so-card__line"><span>{{ $item->quantity }}× {{ $item->name }}</span><span>{{ $item->lineTotal($order->currency)->format() }}</span></div>
                                    @foreach ($item->options as $option)
                                        <div class="so-card__line so-card__line--opt"><span>+ {{ $option->name }}</span></div>
                                    @endforeach
                                @endforeach
                                <div class="so-card__line so-card__line--total">
                                    <span>Total @if ($order->payment_method && $order->payment_method !== 'online')<small class="text-muted">· {{ \Modules\Services\Models\ServiceOrder::PAYMENT_METHODS[$order->payment_method] ?? '' }}</small>@endif</span>
                                    <span>{{ $order->total()->format() }}</span>
                                </div>
                            </div>
                        </div>

                        @if ($nextSteps[$order->status] ?? null)
                            <footer class="so-card__actions">
                                @foreach ($nextSteps[$order->status] as $status => $label)
                                    <form method="POST" action="{{ route('provider.service-orders.status', $order) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $status }}">
                                        <button type="submit" class="btn btn-sm {{ $status === 'cancelled' ? 'btn-danger-outline' : 'btn-primary' }}">{{ $label }}</button>
                                    </form>
                                @endforeach
                            </footer>
                        @endif
                    </article>
                @endforeach
            </div>
            <div style="margin-top: 1rem;">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
@endsection
