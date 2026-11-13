@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Support\Fmt; use Modules\Operations\Models\Shift; @endphp

@section('title', 'Operations')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Overview'])

        @if ($me && $myShifts->isNotEmpty())
            <div class="card ops-card">
                <h3 class="ops-card__title"><x-icon name="clock" /> My shifts</h3>
                <div class="ops-list">
                    @foreach ($myShifts as $shift)
                        @include('operations::shifts.partials.clock', ['shift' => $shift])
                    @endforeach
                </div>
            </div>
        @endif

        @if ($canManage)
            <div class="module-grid ops-stats">
                <div class="card stat-tile">
                    <p>Income this month</p>
                    <h2 class="ops-pos">{{ Fmt::money($month['income']) }}</h2>
                </div>
                <div class="card stat-tile">
                    <p>Expenses this month</p>
                    <h2 class="ops-neg">{{ Fmt::money($month['expense']) }}</h2>
                </div>
                <div class="card stat-tile">
                    <p>Net this month</p>
                    <h2 class="{{ $month['net'] < 0 ? 'ops-neg' : '' }}">{{ Fmt::money($month['net']) }}</h2>
                    <p class="form-hint"><a href="{{ route('ops.finance.report') }}">Full report</a></p>
                </div>
                <div class="card stat-tile">
                    <p>Active staff</p>
                    <h2>{{ $staffCount }}</h2>
                    <p class="form-hint">{{ $onDuty->count() }} on duty now @if ($missedShifts) &middot; <span class="ops-neg">{{ $missedShifts }} missed this week</span>@endif</p>
                </div>
            </div>
        @endif

        @if ($canInventory)
            <div class="module-grid ops-stats">
                <a class="card stat-tile" href="{{ route('ops.inventory.index', ['alert' => 'low']) }}">
                    <p>Low in store</p>
                    <h2 class="{{ $lowStockCount ? 'ops-neg' : '' }}">{{ $lowStockCount }}</h2>
                    <p class="form-hint">items at or below reorder level</p>
                </a>
                <a class="card stat-tile" href="{{ route('ops.inventory.locations.index') }}">
                    <p>Short in rooms</p>
                    <h2 class="{{ $belowPar ? 'ops-neg' : '' }}">{{ $belowPar }}</h2>
                    <p class="form-hint">lines below par level</p>
                </a>
                <a class="card stat-tile ops-action-tile" href="{{ route('ops.inventory.movements.create', ['type' => 'receive']) }}">
                    <p>Quick action</p>
                    <h3>Receive new stock</h3>
                </a>
                <a class="card stat-tile ops-action-tile" href="{{ route('ops.inventory.movements.create', ['type' => 'transfer']) }}">
                    <p>Quick action</p>
                    <h3>Move or issue items</h3>
                </a>
            </div>
        @endif

        <div class="ops-columns">
            <div class="card ops-card">
                <h3 class="ops-card__title"><x-icon name="users" /> Today's roster</h3>
                @forelse ($todayShifts as $shift)
                    <div class="ops-row">
                        <div>
                            <strong>{{ $shift->staffMember->name }}</strong>
                            <div class="form-hint" style="margin:0;">{{ $shift->area ?? $shift->staffMember->position }} &middot; {{ $shift->starts_at->format('g:i A') }} – {{ $shift->ends_at->format('g:i A') }}</div>
                        </div>
                        @include('operations::shifts.partials.status', ['shift' => $shift])
                    </div>
                @empty
                    <p class="text-muted">No shifts scheduled today.</p>
                @endforelse
                <a href="{{ route('ops.shifts.index') }}" class="btn btn-outline btn-sm" style="margin-top:.75rem;">Open roster</a>
            </div>

            @if ($canInventory)
                <div class="card ops-card">
                    <h3 class="ops-card__title"><x-icon name="clipboard" /> Running low in the store</h3>
                    @forelse ($lowStock as $item)
                        <div class="ops-row">
                            <a href="{{ route('ops.inventory.items.show', $item) }}">{{ $item->name }}</a>
                            <span class="badge badge--danger">{{ (int) $item->store_quantity }} / {{ $item->reorder_level }} {{ $item->unit }}</span>
                        </div>
                    @empty
                        <p class="text-muted">Nothing is below its reorder level. Set reorder levels on items to get alerts here.</p>
                    @endforelse
                </div>

                <div class="card ops-card ops-card--wide">
                    <h3 class="ops-card__title"><x-icon name="truck" /> Latest stock movements</h3>
                    @forelse ($recentMovements as $m)
                        @include('operations::inventory.movements.partials.row', ['m' => $m, 'compact' => true])
                    @empty
                        <p class="text-muted">No stock movements yet.</p>
                    @endforelse
                    <a href="{{ route('ops.inventory.movements.index') }}" class="btn btn-outline btn-sm" style="margin-top:.75rem;">All movements</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
