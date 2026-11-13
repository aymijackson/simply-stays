@extends('layouts.app')

@section('title', 'Order details')

@section('content')
<div class="page page--tight">
    <div class="container" style="max-width: 640px;">
        <a href="{{ route('service-orders.index') }}">&larr; Back to orders</a>

        <div class="card" style="margin-top: 1rem;">
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                <x-status-badge :status="$order->status" />
                <x-status-badge :status="$order->isPaid() ? 'paid' : 'pending'" />
            </div>

            <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem;">
                <div style="width: 80px; height: 80px; border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0;">
                    <x-photo :url="$order->service->coverPhotoUrl()" :type="$order->service->category->fulfillment_type ?? 'other'" :seed="$order->service_id" :alt="$order->service->title" />
                </div>
                <div>
                    <h1 style="margin-bottom: 0.15rem;">{{ $order->service->title }}</h1>
                    <p style="margin: 0; display: flex; align-items: center; gap: 0.4rem;">
                        @php $offeredBy = $order->service->offeredBy($order->location?->property ?? $order->booking?->listing?->property); @endphp
                        <span class="avatar" style="width:22px;height:22px;font-size:0.7rem;">{{ strtoupper(mb_substr($offeredBy, 0, 1)) }}</span>
                        {{ $offeredBy }}
                    </p>
                </div>
            </div>

            @if ($order->booking)
                <p>Linked booking: <a href="{{ route('bookings.show', $order->booking) }}">{{ $order->booking->listing->title }}</a></p>
            @endif

            <div style="background: var(--color-bg-alt); border-radius: var(--radius-sm); padding: 1rem; margin-bottom: 1rem;">
                <strong>{{ $handler->summary($order->fulfillment_details ?? []) }}</strong>
            </div>

            @include('services::partials.receipt', ['order' => $order])

            <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem; flex-wrap: wrap;">
                @if (! $order->isPaid() && $order->customer_id === auth()->id() && in_array($order->status, ['pending', 'confirmed']))
                    <a href="{{ route('checkout.choose.service', $order) }}" class="btn btn-primary">Pay now</a>
                @endif

                @if ($canReview)
                    <a href="{{ route('service-orders.review.create', $order) }}" class="btn btn-primary">Leave a review</a>
                @endif

                @if (in_array($order->status, ['pending', 'confirmed']))
                    <form method="POST" action="{{ route('service-orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?');">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-danger-outline">Cancel order</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
