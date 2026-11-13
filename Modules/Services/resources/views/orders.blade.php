@extends('layouts.app')

@section('title', 'My service orders')

@section('content')
<div class="page page--tight">
    <div class="container">
        <h1>My service orders</h1>

        @if ($orders->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin-bottom: 1rem;">You haven't ordered a service yet.</p>
                <a href="{{ route('services.index') }}" class="btn btn-primary">Browse services</a>
            </div>
        @else
            <div class="entity-grid">
                @foreach ($orders as $order)
                    <a href="{{ route('service-orders.show', $order) }}" class="entity-card">
                        <div class="entity-card__media">
                            <x-photo :url="$order->service->coverPhotoUrl()" :type="$order->service->category->fulfillment_type ?? 'other'" :seed="$order->service_id" :alt="$order->service->title" />
                            <span class="entity-card__badge"><x-status-badge :status="$order->status" /></span>
                        </div>
                        <div class="entity-card__body">
                            <div class="entity-card__title">{{ $order->service->title }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
