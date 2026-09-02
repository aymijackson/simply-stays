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
                    <x-photo :type="$order->service->category->fulfillment_type ?? 'other'" :seed="$order->service_id" :alt="$order->service->title" />
                </div>
                <div>
                    <h1 style="margin-bottom: 0.15rem;">{{ $order->service->title }}</h1>
                    <p style="margin: 0; display: flex; align-items: center; gap: 0.4rem;">
                        <span class="avatar" style="width:22px;height:22px;font-size:0.7rem;">{{ strtoupper(substr($order->service->provider->name, 0, 1)) }}</span>
                        {{ $order->service->provider->name }}
                    </p>
                </div>
            </div>

            @if ($order->booking)
                <p>Linked booking: <a href="{{ route('bookings.show', $order->booking) }}">{{ $order->booking->listing->title }}</a></p>
            @endif

            <div style="background: var(--color-bg-alt); border-radius: var(--radius-sm); padding: 1rem; margin-bottom: 1rem;">
                <strong>{{ $handler->summary($order->fulfillment_details ?? []) }}</strong>
            </div>

            @if ($order->items->isNotEmpty())
                <div class="card" style="padding: 1rem; margin-bottom: 1rem;">
                    @foreach ($order->items as $item)
                        <div style="padding: 0.3rem 0;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                                <span>{{ $item->quantity }}&times; {{ $item->name }} @if ($item->is_complimentary) <span class="badge badge--gold">Complimentary</span> @endif</span>
                                <span>{{ $item->lineTotal($order->currency)->format() }}</span>
                            </div>
                            @if ($item->options->isNotEmpty())
                                <div style="margin-left: 1rem; margin-top: 0.2rem;">
                                    @foreach ($item->options as $option)
                                        <div style="display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--color-text-muted);">
                                            <span>+ {{ $option->quantity > 1 ? $option->quantity.'x ' : '' }}{{ $option->name }}</span>
                                            <span>{{ $option->price_delta_amount > 0 ? $option->lineTotal($order->currency)->format() : '' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($order->price_breakdown)
                @php $currency = \Modules\Core\Enums\Currency::from($order->currency); @endphp
                <div style="background: var(--color-bg-alt); border-radius: var(--radius-sm); padding: 0.9rem 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.88rem;"><span>Subtotal</span><span>{{ \Modules\Core\Support\Money::fromMinorUnits($order->price_breakdown['subtotal'], $currency)->format() }}</span></div>
                    @if (($order->price_breakdown['service_charge'] ?? 0) > 0)
                        <div style="display: flex; justify-content: space-between; font-size: 0.88rem;"><span>Service charge</span><span>{{ \Modules\Core\Support\Money::fromMinorUnits($order->price_breakdown['service_charge'], $currency)->format() }}</span></div>
                    @endif
                    @if (($order->price_breakdown['tax'] ?? 0) > 0)
                        <div style="display: flex; justify-content: space-between; font-size: 0.88rem;"><span>Tax</span><span>{{ \Modules\Core\Support\Money::fromMinorUnits($order->price_breakdown['tax'], $currency)->format() }}</span></div>
                    @endif
                    @if (($order->price_breakdown['delivery_fee'] ?? 0) > 0)
                        <div style="display: flex; justify-content: space-between; font-size: 0.88rem;"><span>Delivery fee</span><span>{{ \Modules\Core\Support\Money::fromMinorUnits($order->price_breakdown['delivery_fee'], $currency)->format() }}</span></div>
                    @elseif (isset($order->price_breakdown['delivery_fee']))
                        <div style="display: flex; justify-content: space-between; font-size: 0.88rem; color: var(--color-success);"><span>Delivery</span><span>Free</span></div>
                    @endif
                </div>
            @endif

            <p style="font-size: 1.15rem;"><strong style="color: var(--color-navy-950);">Total: {{ $order->total()->format() }}</strong></p>

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
