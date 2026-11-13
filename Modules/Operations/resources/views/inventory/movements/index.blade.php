@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\InventoryMovement; @endphp

@section('title', 'Stock movements')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Stock movements'])

        <div class="ops-toolbar ops-toolbar--end">
            <div class="ops-toolbar__actions">
                <a href="{{ route('ops.inventory.movements.create') }}" class="btn btn-primary">Record movement</a>
            </div>
        </div>

        <form method="GET" class="ops-filters" role="search">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control ops-filters__search" placeholder="Item name">
            <select name="type" class="form-control">
                <option value="">All types</option>
                @foreach (InventoryMovement::TYPES as $key => [$label])
                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="location" class="form-control">
                <option value="">Any location</option>
                @foreach ($locations->groupBy(fn ($l) => $l->type === 'apartment' ? $l->name : 'Store & service areas') as $group => $groupLocations)
                    <optgroup label="{{ $group }}">
                        @foreach ($groupLocations as $loc)
                            <option value="{{ $loc->getRouteKey() }}" @selected(request('location') === $loc->getRouteKey())>{{ $loc->type === 'apartment' ? ($loc->area ?? $loc->name) : $loc->label() }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="From">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="To">
            <button class="btn btn-primary">Search</button>
        </form>

        <div class="card ops-card">
            @php $lastDay = null; @endphp
            @forelse ($movements as $m)
                @if ($lastDay !== $m->occurred_at->toDateString())
                    @php $lastDay = $m->occurred_at->toDateString(); @endphp
                    <p class="ops-day">{{ $m->occurred_at->format('l, j F Y') }}</p>
                @endif
                @include('operations::inventory.movements.partials.row', ['m' => $m])
            @empty
                <p class="text-muted">No movements match.</p>
            @endforelse
        </div>
        <div style="margin-top: 1rem;">{{ $movements->links() }}</div>
    </div>
</div>
@endsection
