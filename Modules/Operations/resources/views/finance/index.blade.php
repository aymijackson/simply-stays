@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\FinanceTransaction; use Modules\Operations\Support\Fmt; @endphp

@section('title', 'Accounting')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Accounting'])
        @include('operations::finance.partials.nav')

        <div class="ops-toolbar">
            <form method="GET" class="gform-filters">
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="From">
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="To">
                <select name="type" class="form-control">
                    <option value="">Income &amp; expenses</option>
                    <option value="income" @selected(request('type') === 'income')>Income only</option>
                    <option value="expense" @selected(request('type') === 'expense')>Expenses only</option>
                </select>
                <select name="category" class="form-control">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ ucfirst($category->type) }}: {{ $category->name }}</option>
                    @endforeach
                </select>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search">
                <button class="btn btn-outline">Filter</button>
            </form>
            <div class="ops-toolbar__actions">
                <a href="{{ route('ops.finance.export', request()->query() + ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-ghost">Export CSV</a>
                <a href="{{ route('ops.finance.create', ['type' => 'income']) }}" class="btn btn-outline">+ Income</a>
                <a href="{{ route('ops.finance.create', ['type' => 'expense']) }}" class="btn btn-primary">+ Expense</a>
            </div>
        </div>

        @if ($pendingBookings)
            <div class="card ops-restock">
                <div><strong>{{ $pendingBookings }} confirmed booking(s)</strong> from the website are not in the books yet.</div>
                <form method="POST" action="{{ route('ops.finance.sync-bookings') }}">@csrf<button class="btn btn-primary">Post as room revenue</button></form>
            </div>
        @endif

        <div class="module-grid ops-stats">
            <div class="card stat-tile"><p>Income</p><h2 class="ops-pos">{{ Fmt::money($income) }}</h2></div>
            <div class="card stat-tile"><p>Expenses</p><h2 class="ops-neg">{{ Fmt::money($expense) }}</h2></div>
            <div class="card stat-tile"><p>Net</p><h2 class="{{ $income - $expense < 0 ? 'ops-neg' : '' }}">{{ Fmt::money($income - $expense) }}</h2><p class="form-hint">{{ $from->format('j M') }} – {{ $to->format('j M Y') }}</p></div>
        </div>

        <div class="card card--flush ops-table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Date</th><th>Category</th><th>Details</th><th>Method</th><th class="ops-num">Amount</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $t)
                        <tr>
                            <td class="ops-nowrap">{{ $t->occurred_on->format('j M Y') }}</td>
                            <td>{{ $t->category->name }}</td>
                            <td>
                                {{ $t->description ?: '—' }}
                                <div class="form-hint" style="margin:0;">
                                    @if ($t->counterparty){{ $t->counterparty }}@endif
                                    @if ($t->reference) &middot; {{ $t->reference }}@endif
                                    @if ($t->media_count) &middot; <x-icon name="clipboard" style="width:12px;height:12px;" /> receipt @endif
                                </div>
                            </td>
                            <td class="text-muted">{{ FinanceTransaction::PAYMENT_METHODS[$t->payment_method] ?? $t->payment_method }}</td>
                            <td class="ops-num ops-nowrap {{ $t->type === 'income' ? 'ops-pos' : 'ops-neg' }}"><strong>{{ $t->type === 'income' ? '+' : '−' }}{{ $t->money()->format() }}</strong></td>
                            <td><a href="{{ route('ops.finance.edit', $t) }}" class="btn btn-ghost btn-sm">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No entries in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem;">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
