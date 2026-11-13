<div class="ops-subnav">
    <a href="{{ route('ops.finance.index') }}" @class(['is-active' => request()->routeIs('ops.finance.index')])>Transactions</a>
    <a href="{{ route('ops.finance.report') }}" @class(['is-active' => request()->routeIs('ops.finance.report')])>Profit &amp; loss</a>
    <a href="{{ route('ops.finance.categories') }}" @class(['is-active' => request()->routeIs('ops.finance.categories')])>Categories</a>
</div>
