@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\InventoryItem; use Modules\Operations\Support\Fmt; @endphp

@section('title', 'Stock')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Stock'])

        <div class="ops-kinds">
            <a href="{{ route('ops.inventory.index') }}" class="ops-kind @if (! request('kind') && ! request('alert')) is-active @endif">All <span>{{ $kindCounts->sum() }}</span></a>
            @foreach (InventoryItem::KINDS as $key => $label)
                <a href="{{ route('ops.inventory.index', ['kind' => $key]) }}" class="ops-kind @if (request('kind') === $key) is-active @endif" title="{{ InventoryItem::KIND_HINTS[$key] }}">{{ $label }}s <span>{{ $kindCounts[$key] ?? 0 }}</span></a>
            @endforeach
            <a href="{{ route('ops.inventory.index', ['alert' => 'low']) }}" class="ops-kind ops-kind--alert @if (request('alert') === 'low') is-active @endif">Low in store <span>{{ $lowStockCount }}</span></a>
        </div>

        <div class="ops-toolbar">
            <form method="GET" class="gform-filters">
                @if (request('kind'))<input type="hidden" name="kind" value="{{ request('kind') }}">@endif
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search items">
                <select name="location" class="form-control">
                    <option value="">All locations</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected($location?->id === $loc->id)>{{ $loc->label() }}</option>
                    @endforeach
                </select>
                <select name="category" class="form-control">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option @selected(request('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline">Filter</button>
            </form>
            <div class="ops-toolbar__actions">
                <a href="{{ route('ops.inventory.movements.create') }}" class="btn btn-outline">Record movement</a>
                @if ($canManage)
                    <a href="{{ route('ops.inventory.items.create', ['kind' => request('kind', 'asset')]) }}" class="btn btn-primary">Add item</a>
                @endif
            </div>
        </div>

        @if ($items->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p>No items match.</p>
            </div>
        @else
            <div class="card card--flush ops-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Category</th>
                            @if ($location)<th class="ops-num">In {{ $location->label() }}</th>@endif
                            <th class="ops-num">In store</th>
                            <th class="ops-num">Total</th>
                            <th class="ops-num">Unit cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php $low = $item->reorder_level !== null && (int) $item->store_quantity <= $item->reorder_level; @endphp
                            <tr @class(['ops-muted-row' => ! $item->is_active])>
                                <td><a href="{{ route('ops.inventory.items.show', $item) }}"><strong>{{ $item->name }}</strong></a>@unless ($item->is_active) <span class="badge badge--dark">Archived</span>@endunless</td>
                                <td><span class="ops-kind-dot ops-kind-dot--{{ $item->kind }}"></span>{{ $item->kindLabel() }}</td>
                                <td class="text-muted">{{ $item->category ?? '—' }}</td>
                                @if ($location)<td class="ops-num"><strong>{{ (int) $item->location_quantity }}</strong></td>@endif
                                <td class="ops-num">
                                    @if ($low)
                                        <span class="badge badge--danger" title="Reorder level {{ $item->reorder_level }}">{{ (int) $item->store_quantity }}</span>
                                    @else
                                        {{ (int) $item->store_quantity }}
                                    @endif
                                </td>
                                <td class="ops-num">{{ (int) $item->total_quantity }} <span class="text-muted">{{ $item->unit }}</span></td>
                                <td class="ops-num text-muted">{{ $item->unit_cost_amount ? Fmt::money($item->unit_cost_amount) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1rem;">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
