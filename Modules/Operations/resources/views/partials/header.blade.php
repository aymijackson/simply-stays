{{-- Property switcher + operations section tabs. Expects $opsProperty, $opsProperties, $opsLevel (view composer). --}}
@php
    $rank = ['basic' => 1, 'inventory' => 2, 'manager' => 3, 'owner' => 4][$opsLevel ?? 'basic'] ?? 1;
    $tabs = array_filter([
        ['Overview', 'ops.dashboard', 'ops.dashboard', 1],
        ['Stock', 'ops.inventory.index', 'ops.inventory.index', 2],
        ['Locations', 'ops.inventory.locations.index', 'ops.inventory.locations.*', 2],
        ['Movements', 'ops.inventory.movements.index', 'ops.inventory.movements.*', 2],
        ['Shifts', 'ops.shifts.index', 'ops.shifts.*', 1],
        ['Staff', 'ops.staff.index', 'ops.staff.*', 3],
        ['Accounting', 'ops.finance.index', 'ops.finance.*', 3],
    ], fn ($t) => $rank >= $t[3]);
@endphp
<div class="ops-header">
    <div class="ops-header__top">
        <div>
            <p class="ops-header__eyebrow">Operations</p>
            <h1 class="ops-header__title">{{ $title ?? 'Overview' }}</h1>
        </div>
        @if (($opsProperties ?? collect())->count() > 1)
            <form method="GET" action="{{ url()->current() }}" class="ops-switcher">
                <label class="sr-only" for="ops-property">Property</label>
                <x-icon name="building" style="width:16px;height:16px;" />
                <select id="ops-property" name="property" class="form-control" onchange="this.form.submit()">
                    @foreach ($opsProperties as $p)
                        <option value="{{ $p->id }}" @selected($opsProperty?->id === $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </form>
        @elseif ($opsProperty)
            <span class="ops-switcher ops-switcher--static"><x-icon name="building" style="width:16px;height:16px;" /> {{ $opsProperty->name }}</span>
        @endif
    </div>
    <nav class="ops-tabs" aria-label="Operations sections">
        @foreach ($tabs as [$label, $route, $pattern])
            <a href="{{ route($route) }}" class="ops-tab @if (request()->routeIs($pattern) || ($route === 'ops.inventory.index' && request()->routeIs('ops.inventory.items.*'))) is-active @endif">{{ $label }}</a>
        @endforeach
    </nav>
</div>

@if ($errors->any())
    <x-alert type="danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </x-alert>
@endif
