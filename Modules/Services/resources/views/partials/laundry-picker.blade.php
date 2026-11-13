{{-- Laundry order grid. Expects $service, $priceList (PriceList::for), optional $property for the charges note. --}}
@php
    $currency = \Modules\Core\Enums\Currency::from($service->currency);
    $fmt = fn ($minor) => \Modules\Core\Support\Money::fromMinorUnits($minor, $currency)->format();
    $garments = $priceList['garments']->filter(fn ($g) => $g['item']->is_available && collect($g['prices'])->contains('available', true));
    $treatments = collect($priceList['treatments'])->filter(fn ($t) => $garments->contains(fn ($g) => ($g['prices'][$t]['available'] ?? false)))->values();
    $oldLaundry = old('laundry', []);
@endphp

<div class="form-group" data-laundry>
    <label class="form-label">What are we washing?</label>
    <p class="form-hint" style="margin-top: 0;">Enter how many pieces need each treatment. Prices are per piece.</p>

    @if ($garments->isEmpty())
        <x-alert type="danger">The price list isn't ready yet. Please contact us to order.</x-alert>
    @else
        <div class="lp-table-wrap">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        @foreach ($treatments as $treatment)<th class="lp-num">{{ $treatment }}</th>@endforeach
                    </tr>
                </thead>
                @foreach ($garments->groupBy(fn ($g) => $g['item']->category ?: 'Items') as $group => $groupGarments)
                    <tbody>
                        @if ($garments->pluck('item.category')->filter()->unique()->count() > 1)
                            <tr class="lp-group"><th colspan="{{ $treatments->count() + 1 }}">{{ $group }}</th></tr>
                        @endif
                        @foreach ($groupGarments as $g)
                            <tr>
                                <th scope="row">{{ $g['item']->name }}</th>
                                @foreach ($treatments as $treatment)
                                    @php $price = $g['prices'][$treatment] ?? null; @endphp
                                    <td class="lp-num" data-label="{{ $treatment }}">
                                        @if ($price && $price['available'])
                                            <label class="lp-cell">
                                                <span class="lp-price">{{ $fmt($price['amount']) }}</span>
                                                <input type="number" min="0" max="500" inputmode="numeric"
                                                       name="laundry[{{ $g['item']->id }}][{{ $price['option_id'] }}]"
                                                       value="{{ $oldLaundry[$g['item']->id][$price['option_id']] ?? '' }}"
                                                       class="form-control lp-qty" placeholder="0" data-price="{{ $price['amount'] }}"
                                                       aria-label="{{ $g['item']->name }}, {{ $treatment }}, {{ $fmt($price['amount']) }} each">
                                            </label>
                                        @else
                                            <span class="text-muted" title="Not offered">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>

        <div class="lp-total" aria-live="polite">
            <span><strong data-laundry-count>0</strong> pieces</span>
            <span>Subtotal <strong data-laundry-subtotal>{{ $fmt(0) }}</strong></span>
        </div>
        @include('services::partials.charges-note', ['service' => $service, 'property' => $property ?? null])
    @endif
</div>

@once
    @push('scripts')
    <script>
    (function () {
        var symbol = @json($currency->symbol());
        function money(minor) {
            return symbol + (minor / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function update(wrap) {
            var count = 0, subtotal = 0;
            wrap.querySelectorAll('.lp-qty').forEach(function (input) {
                var qty = Math.max(0, parseInt(input.value, 10) || 0);
                count += qty;
                subtotal += qty * parseInt(input.dataset.price, 10);
                input.closest('td').classList.toggle('is-picked', qty > 0);
            });
            wrap.querySelector('[data-laundry-count]').textContent = count;
            wrap.querySelector('[data-laundry-subtotal]').textContent = money(subtotal);
        }
        document.querySelectorAll('[data-laundry]').forEach(function (wrap) {
            if (!wrap.querySelector('[data-laundry-count]')) { return; }
            wrap.addEventListener('input', function () { update(wrap); });
            update(wrap);
        });
    })();
    </script>
    @endpush
@endonce
