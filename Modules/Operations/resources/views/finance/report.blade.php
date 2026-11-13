@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Support\Fmt; @endphp

@section('title', 'Profit & loss')

@section('content')
@php $peak = max(1, $monthly->max(fn ($m) => max($m['income'], $m['expense']))); @endphp
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Accounting'])
        @include('operations::finance.partials.nav')

        <div class="ops-toolbar ops-toolbar--end">
            <div class="ops-toolbar__actions">
                <a href="{{ route('ops.finance.report', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]) }}" class="btn btn-ghost btn-sm">This month</a>
                <a href="{{ route('ops.finance.report', ['from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => now()->subMonthNoOverflow()->endOfMonth()->toDateString()]) }}" class="btn btn-ghost btn-sm">Last month</a>
                <a href="{{ route('ops.finance.report', ['from' => now()->startOfYear()->toDateString(), 'to' => now()->toDateString()]) }}" class="btn btn-ghost btn-sm">Year to date</a>
                <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">Print</button>
            </div>
        </div>

        <form method="GET" class="ops-filters" role="search">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="From">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="To">
            <button class="btn btn-primary">Show</button>
        </form>

        <div class="card ops-statement">
            <h2>Income and expenditure</h2>
            <p class="text-muted">{{ $opsProperty?->name }} &middot; {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }}</p>

            <table class="ops-statement__table">
                <tr class="ops-statement__head"><th colspan="2">Income</th></tr>
                @forelse ($summary['income_by_category'] as $row)
                    <tr><td>{{ $row->category }} <span class="text-muted">({{ $row->entries }})</span></td><td class="ops-num">{{ Fmt::money((int) $row->total) }}</td></tr>
                @empty
                    <tr><td class="text-muted" colspan="2">No income recorded.</td></tr>
                @endforelse
                <tr class="ops-statement__sub"><td>Total income</td><td class="ops-num">{{ Fmt::money($summary['income']) }}</td></tr>

                <tr class="ops-statement__head"><th colspan="2">Expenses</th></tr>
                @forelse ($summary['expense_by_category'] as $row)
                    <tr><td>{{ $row->category }} <span class="text-muted">({{ $row->entries }})</span></td><td class="ops-num">{{ Fmt::money((int) $row->total) }}</td></tr>
                @empty
                    <tr><td class="text-muted" colspan="2">No expenses recorded.</td></tr>
                @endforelse
                <tr class="ops-statement__sub"><td>Total expenses</td><td class="ops-num">{{ Fmt::money($summary['expense']) }}</td></tr>

                <tr class="ops-statement__net {{ $summary['net'] < 0 ? 'is-loss' : '' }}"><td>{{ $summary['net'] < 0 ? 'Net loss' : 'Net profit' }}</td><td class="ops-num">{{ Fmt::money($summary['net']) }}</td></tr>
            </table>
        </div>

        <div class="card ops-card" style="margin-top:1.25rem;">
            <h3 class="ops-card__title">Last 12 months</h3>
            <div class="ops-bars" role="img" aria-label="Monthly income and expenses">
                @foreach ($monthly as $m)
                    <div class="ops-bars__month" title="{{ $m['month']->format('F Y') }}: income {{ Fmt::money($m['income']) }}, expenses {{ Fmt::money($m['expense']) }}">
                        <div class="ops-bars__pair">
                            <span class="ops-bars__bar ops-bars__bar--in" style="height: {{ round($m['income'] / $peak * 100, 1) }}%"></span>
                            <span class="ops-bars__bar ops-bars__bar--out" style="height: {{ round($m['expense'] / $peak * 100, 1) }}%"></span>
                        </div>
                        <span class="ops-bars__label">{{ $m['month']->format('M') }}</span>
                    </div>
                @endforeach
            </div>
            <p class="form-hint ops-legend"><span class="ops-swatch ops-swatch--in"></span> Income <span class="ops-swatch ops-swatch--out"></span> Expenses</p>

            <div class="ops-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Month</th><th class="ops-num">Income</th><th class="ops-num">Expenses</th><th class="ops-num">Net</th></tr></thead>
                    <tbody>
                        @foreach ($monthly->reverse() as $m)
                            <tr>
                                <td>{{ $m['month']->format('F Y') }}</td>
                                <td class="ops-num">{{ Fmt::money($m['income']) }}</td>
                                <td class="ops-num">{{ Fmt::money($m['expense']) }}</td>
                                <td class="ops-num {{ $m['net'] < 0 ? 'ops-neg' : '' }}"><strong>{{ Fmt::money($m['net']) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
