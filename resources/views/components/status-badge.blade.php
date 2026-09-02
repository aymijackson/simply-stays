@props(['status'])

@php
    $tone = match ($status) {
        'confirmed', 'completed', 'published', 'paid', 'active', 'succeeded' => 'success',
        'cancelled', 'failed', 'hidden', 'suspended', 'refunded' => 'danger',
        'pending', 'draft', 'in_progress', 'approved' => 'gold',
        default => 'primary',
    };
    $label = ucfirst(str_replace('_', ' ', $status));
@endphp

@if ($tone === 'primary')
    <span {{ $attributes->merge(['class' => 'badge']) }}>{{ $label }}</span>
@else
    <span {{ $attributes->merge(['class' => 'badge badge--'.$tone]) }}>{{ $label }}</span>
@endif
