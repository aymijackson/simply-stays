{{-- A movement as a sentence: "12 Glass cup · Store → Kitchen". --}}
@php
    $sign = match ($m->type) {
        'receive' => '+',
        'consume', 'write_off' => '−',
        'adjust' => $m->quantity > 0 ? '+' : '−',
        default => '',
    };
@endphp
<div class="ops-row ops-move ops-move--{{ $m->type }}">
    <div>
        <strong>{{ $sign }}{{ abs($m->quantity) }} {{ $m->item?->unit }}</strong>
        <a href="{{ route('ops.inventory.items.show', $m->item) }}">{{ $m->item?->name }}</a>
        <div class="form-hint" style="margin:0;">
            {{ $m->typeLabel() }}:
            @if ($m->fromLocation) {{ $m->fromLocation->label() }} @endif
            @if ($m->fromLocation && $m->toLocation) &rarr; @endif
            @if ($m->toLocation) {{ $m->toLocation->label() }} @endif
            @if (! ($compact ?? false))
                @if ($m->staffMember) &middot; by {{ $m->staffMember->name }} @endif
                @if ($m->note) &middot; {{ $m->note }} @endif
            @endif
        </div>
    </div>
    <span class="text-muted ops-nowrap">{{ $m->occurred_at->format('j M, g:i A') }}</span>
</div>
