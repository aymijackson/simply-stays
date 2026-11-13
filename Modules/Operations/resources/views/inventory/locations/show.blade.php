@extends(auth()->user()->portalLayout())

@section('title', $location->label())

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => $location->label()])

        @php $short = $stocks->filter(fn ($s) => $s->shortfall() > 0); @endphp

        <div class="ops-toolbar">
            <div class="text-muted">
                {{ \Modules\Operations\Models\InventoryLocation::TYPES[$location->type] ?? $location->type }}
                @if ($location->listing) &middot; {{ $location->listing->title }} @endif
                &middot; {{ $stocks->where('quantity', '>', 0)->count() }} items, {{ $stocks->sum('quantity') }} pieces
            </div>
            <div class="ops-toolbar__actions">
                <a href="{{ route('ops.inventory.movements.create', ['type' => 'transfer', 'to' => $location->id]) }}" class="btn btn-outline">Bring items here</a>
                <a href="{{ route('ops.inventory.movements.create', ['type' => 'consume', 'from' => $location->id]) }}" class="btn btn-outline">Record usage</a>
                @if ($canManage)<a href="{{ route('ops.inventory.locations.edit', $location) }}" class="btn btn-ghost">Edit</a>@endif
            </div>
        </div>

        @if ($short->isNotEmpty() && $stores->isNotEmpty())
            <div class="card ops-restock">
                <div>
                    <strong>{{ $short->count() }} item(s) below par</strong>
                    <div class="form-hint" style="margin:0;">{{ $short->map(fn ($s) => $s->shortfall().' '.$s->item->name)->implode(', ') }}</div>
                </div>
                <form method="POST" action="{{ route('ops.inventory.locations.restock', $location) }}" class="gform-filters" style="margin:0;">
                    @csrf
                    <select name="store_id" class="form-control" aria-label="Take from">
                        @foreach ($stores as $store)<option value="{{ $store->id }}">From {{ $store->label() }}</option>@endforeach
                    </select>
                    <button class="btn btn-primary">Restock to par</button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('ops.inventory.locations.count', $location) }}">
            @csrf
            <div class="card card--flush ops-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th class="ops-num">On record</th>
                            <th class="ops-num">Par</th>
                            <th class="ops-num">Counted now</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stocks as $stock)
                            <tr>
                                <td><a href="{{ route('ops.inventory.items.show', $stock->item) }}">{{ $stock->item->name }}</a></td>
                                <td class="text-muted"><span class="ops-kind-dot ops-kind-dot--{{ $stock->item->kind }}"></span>{{ $stock->item->kindLabel() }}</td>
                                <td class="ops-num">
                                    <strong class="{{ $stock->shortfall() ? 'ops-neg' : '' }}">{{ $stock->quantity }}</strong> <span class="text-muted">{{ $stock->item->unit }}</span>
                                </td>
                                <td class="ops-num">
                                    @if ($canManage)
                                        <input type="number" min="0" name="par[{{ $stock->id }}]" value="{{ $stock->par_level }}" class="form-control ops-qty" aria-label="Par level for {{ $stock->item->name }}">
                                    @else
                                        {{ $stock->par_level ?? '—' }}
                                    @endif
                                </td>
                                <td class="ops-num">
                                    <input type="number" min="0" name="counted[{{ $stock->id }}]" class="form-control ops-qty" placeholder="{{ $stock->quantity }}" aria-label="Counted {{ $stock->item->name }}">
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Nothing tracked here yet. Add an item below.</td></tr>
                        @endforelse
                        <tr class="ops-add-row">
                            <td colspan="2">
                                <select name="new_item_id" class="form-control" aria-label="Start tracking another item here">
                                    <option value="">+ Track another item here…</option>
                                    @foreach ($otherItems as $other)<option value="{{ $other->id }}">{{ $other->name }}</option>@endforeach
                                </select>
                            </td>
                            <td></td>
                            <td class="ops-num">@if ($canManage)<input type="number" min="0" name="new_par" class="form-control ops-qty" placeholder="Par">@endif</td>
                            <td class="ops-num"><input type="number" min="0" name="new_quantity" class="form-control ops-qty" placeholder="Qty"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="ops-form-actions">
                <p class="form-hint" style="margin:0; flex:1;">Leave "Counted now" blank where the record is right. Differences are saved as stock-count corrections in the movement log.</p>
                <button class="btn btn-primary">Save count</button>
            </div>
        </form>
    </div>
</div>
@endsection
