@extends(auth()->user()->portalLayout())

@section('title', 'Incoming orders')

@php
    $nextSteps = [
        'pending' => ['confirmed' => 'Confirm', 'cancelled' => 'Decline'],
        'confirmed' => ['in_progress' => 'Start', 'cancelled' => 'Cancel'],
        'in_progress' => ['completed' => 'Mark complete', 'cancelled' => 'Cancel'],
    ];
@endphp

@section('content')
<div class="page page--tight">
    <div class="container">
        @php $isAdmin = auth()->user()->hasRole('Admin'); @endphp
        <a href="{{ route('provider.services.index') }}">&larr; Back to {{ $isAdmin ? 'all services' : 'my services' }}</a>

        <h1 style="margin-top: 1rem;">{{ $isAdmin ? 'All service orders' : 'Incoming orders' }}</h1>

        @if ($orders->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin: 0;">No orders yet.</p>
            </div>
        @else
            <div class="entity-grid">
                @foreach ($orders as $order)
                    <div class="card">
                        <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                            <span class="avatar">{{ strtoupper(substr($order->customer->name, 0, 1)) }}</span>
                            <div>
                                <div class="entity-card__title" style="margin: 0;">{{ $order->service->title }}</div>
                                <div class="entity-card__meta" style="margin: 0;">
                                    {{ $order->customer->name }}
                                    @if ($isAdmin && $order->service->provider) &middot; by {{ $order->service->provider->name }} @endif
                                </div>
                            </div>
                        </div>
                        <x-status-badge :status="$order->status" />

                        <div style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                            @foreach ($nextSteps[$order->status] ?? [] as $status => $label)
                                <form method="POST" action="{{ route('provider.service-orders.status', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $status }}">
                                    <button type="submit" class="btn btn-outline btn-sm">{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
