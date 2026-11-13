{{-- Order lines and bill. Expects $order with items.options loaded. --}}
@php
    $currency = \Modules\Core\Enums\Currency::from($order->currency);
    $fmt = fn ($minor) => \Modules\Core\Support\Money::fromMinorUnits((int) $minor, $currency)->format();
    $b = $order->price_breakdown;
    $row = 'display: flex; justify-content: space-between; gap: 1rem; font-size: 0.88rem;';
@endphp

@if ($order->items->isNotEmpty())
    <div class="card" style="padding: 1rem; margin-bottom: 1rem;">
        @foreach ($order->items as $item)
            <div style="padding: 0.3rem 0;">
                <div style="display: flex; justify-content: space-between; gap: 1rem; font-size: 0.9rem;">
                    <span>
                        {{ $item->quantity }}&times; {{ $item->name }}
                        @if ($item->quantity > 1 && $item->unit_price_amount > 0)<span class="text-muted" style="font-size: 0.8rem;">@ {{ $fmt($item->unit_price_amount) }}</span>@endif
                        @if ($item->is_complimentary) <span class="badge badge--gold">Complimentary</span> @endif
                    </span>
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

@if ($b)
    <div style="background: var(--color-bg-alt); border-radius: var(--radius-sm); padding: 0.9rem 1rem; margin-bottom: 1rem;">
        <div style="{{ $row }}"><span>Subtotal</span><span>{{ $fmt($b['subtotal']) }}</span></div>
        @if (($b['service_charge'] ?? 0) > 0)
            <div style="{{ $row }}"><span>Service charge</span><span>{{ $fmt($b['service_charge']) }}</span></div>
        @endif
        @if (($b['tax'] ?? 0) > 0)
            <div style="{{ $row }}"><span>VAT / tax</span><span>{{ $fmt($b['tax']) }}</span></div>
        @endif
        @if (($b['other_charge'] ?? 0) > 0)
            <div style="{{ $row }}"><span>{{ $b['other_charge_label'] ?? 'Other charge' }}</span><span>{{ $fmt($b['other_charge']) }}</span></div>
        @endif
        @if (($b['delivery_fee'] ?? 0) > 0)
            <div style="{{ $row }}"><span>Delivery fee</span><span>{{ $fmt($b['delivery_fee']) }}</span></div>
        @elseif (isset($b['delivery_fee']))
            <div style="{{ $row }} color: var(--color-success);"><span>Delivery</span><span>Free</span></div>
        @endif
    </div>
@endif

<p style="font-size: 1.15rem; margin-bottom: 0.25rem;"><strong style="color: var(--color-navy-950);">Total: {{ $order->total()->format() }}</strong></p>
@if ($order->payment_method && $order->payment_method !== 'online')
    <p class="text-muted" style="margin-top: 0;">{{ \Modules\Services\Models\ServiceOrder::PAYMENT_METHODS[$order->payment_method] ?? '' }}</p>
@endif
