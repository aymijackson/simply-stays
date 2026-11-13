{{-- Tells the customer which charges will be added. Expects $service, optional $property. --}}
@php
    $rates = $service->chargeRates($property ?? null);
    $fmtPct = fn ($p) => rtrim(rtrim(number_format($p, 2), '0'), '.').'%';
    $parts = array_filter([
        $rates['service_charge'] > 0 ? 'service charge '.$fmtPct($rates['service_charge']) : null,
        $rates['tax'] > 0 ? 'VAT '.$fmtPct($rates['tax']) : null,
        $rates['other'] > 0 ? strtolower($rates['other_label']).' '.$fmtPct($rates['other']) : null,
    ]);
    $inheritsProperty = ! isset($property) && ($service->service_charge_percent === null || $service->tax_percent === null);
@endphp
@if ($parts)
    <p class="form-hint">Plus {{ implode(', ', $parts) }}{{ $service->usesPriceList() ? ' and any delivery fee' : '' }}. Your receipt shows the full bill.</p>
@elseif ($inheritsProperty)
    <p class="form-hint">The property's service charge and VAT are added when the order is for a room or stay.</p>
@endif
