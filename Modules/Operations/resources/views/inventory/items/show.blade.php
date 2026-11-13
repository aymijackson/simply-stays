@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Support\Fmt; @endphp

@section('title', $item->name)

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => $item->name])

        <div class="ops-toolbar">
            <div>
                <span class="badge"><span class="ops-kind-dot ops-kind-dot--{{ $item->kind }}"></span>{{ $item->kindLabel() }}</span>
                @if ($item->category)<span class="badge">{{ $item->category }}</span>@endif
                @unless ($item->is_active)<span class="badge badge--dark">Archived</span>@endunless
                <span class="text-muted">&middot; counted in {{ $item->unit }}
                    @if ($item->unit_cost_amount) &middot; {{ Fmt::money($item->unit_cost_amount) }} each @endif
                    @if ($item->reorder_level !== null) &middot; reorder at {{ $item->reorder_level }} @endif
                </span>
            </div>
            <div class="ops-toolbar__actions">
                <a href="{{ route('ops.inventory.movements.create', ['type' => 'receive', 'item' => $item]) }}" class="btn btn-outline">Receive</a>
                <a href="{{ route('ops.inventory.movements.create', ['type' => 'transfer', 'item' => $item]) }}" class="btn btn-outline">Move / issue</a>
                @if ($canManage)<a href="{{ route('ops.inventory.items.edit', $item) }}" class="btn btn-primary">Edit</a>@endif
            </div>
        </div>

        @if ($item->notes)<p class="text-muted">{{ $item->notes }}</p>@endif

        <div class="ops-columns">
            <div class="card ops-card">
                <h3 class="ops-card__title">Where it is</h3>
                @php $total = $stocks->sum('quantity'); @endphp
                @forelse ($stocks as $stock)
                    <div class="ops-row">
                        <a href="{{ route('ops.inventory.locations.show', $stock->location) }}">{{ $stock->location->label() }}</a>
                        <span>
                            <strong>{{ $stock->quantity }}</strong>
                            @if ($stock->par_level !== null)
                                <span class="{{ $stock->shortfall() ? 'ops-neg' : 'text-muted' }}">/ {{ $stock->par_level }} par</span>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-muted">Not in any location yet.</p>
                @endforelse
                <div class="ops-row ops-row--total"><span>Total</span><strong>{{ $total }} {{ $item->unit }}</strong></div>
                @if ($item->unit_cost_amount && $total > 0)
                    <p class="form-hint">Stock value ≈ {{ Fmt::money($item->unit_cost_amount * $total) }}</p>
                @endif
            </div>

            <div class="card ops-card ops-card--wide">
                <h3 class="ops-card__title">History</h3>
                @forelse ($movements as $m)
                    @include('operations::inventory.movements.partials.row', ['m' => $m])
                @empty
                    <p class="text-muted">No movements recorded.</p>
                @endforelse
                <div style="margin-top: 1rem;">{{ $movements->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
