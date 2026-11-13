@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\Shift; use Illuminate\Support\Str; @endphp

@section('title', 'Shifts')

@section('content')
@php
    $keep = array_filter(request()->only(['q', 'department', 'area']));
    $weekUrl = fn ($date) => route('ops.shifts.index', $keep + ['week' => $date->toDateString()]);
    $isThisWeek = $weekStart->isSameDay(now()->startOfWeek());
    $statusLabel = ['on' => 'On duty', 'completed' => 'Done', 'missed' => 'Missed', 'cancelled' => 'Cancelled', 'scheduled' => null, 'in_progress' => 'On duty'];
@endphp
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Shift roster'])

        {{-- Row 1: week navigation + actions --}}
        <div class="ops-toolbar">
            <div class="ops-weeknav">
                <div class="ops-weeknav__buttons">
                    <a href="{{ $weekUrl($weekStart->subWeek()) }}" class="ops-weeknav__btn" aria-label="Previous week"><x-icon name="chevron-left" /></a>
                    <a href="{{ route('ops.shifts.index', $keep) }}" class="ops-weeknav__btn ops-weeknav__today @if ($isThisWeek) is-current @endif">This week</a>
                    <a href="{{ $weekUrl($weekStart->addWeek()) }}" class="ops-weeknav__btn" aria-label="Next week"><x-icon name="chevron-right" /></a>
                </div>
                <h2 class="ops-weeknav__range">
                    {{ $weekStart->format($weekStart->month === $weekStart->endOfWeek()->month ? 'j' : 'j M') }} – {{ $weekStart->endOfWeek()->format('j M Y') }}
                    <span>Week {{ $weekStart->isoWeek() }}</span>
                </h2>
            </div>
            @if ($canManage)
                <div class="ops-toolbar__actions">
                    <a href="{{ route('ops.shifts.attendance') }}" class="btn btn-outline">Attendance</a>
                    <a href="{{ route('ops.shifts.create', ['date' => ($isThisWeek ? today() : $weekStart)->toDateString()]) }}" class="btn btn-primary">Schedule shift</a>
                </div>
            @endif
        </div>

        {{-- Row 2: filters --}}
        <form method="GET" class="ops-filters" role="search">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control ops-filters__search" placeholder="Search staff" aria-label="Search staff">
            <select name="department" class="form-control" aria-label="Department" onchange="this.form.submit()">
                <option value="">All departments</option>
                @foreach ($departments as $dept)
                    <option @selected(request('department') === $dept)>{{ $dept }}</option>
                @endforeach
            </select>
            <select name="area" class="form-control" aria-label="Duty" onchange="this.form.submit()">
                <option value="">Any duty</option>
                @foreach ($areas as $area)
                    <option @selected(request('area') === $area)>{{ $area }}</option>
                @endforeach
            </select>
            <label class="ops-filters__date">
                <span>Jump to</span>
                <input type="date" name="week" value="{{ $weekStart->toDateString() }}" class="form-control" aria-label="Jump to week containing date" onchange="this.form.submit()">
            </label>
            <button class="btn btn-primary">Search</button>
            @if ($filtered)
                <a href="{{ route('ops.shifts.index', ['week' => $weekStart->toDateString()]) }}" class="ops-filters__clear">Clear filters</a>
            @endif
        </form>

        {{-- Week at a glance --}}
        <div class="ops-roster-summary">
            <span><strong>{{ $summary['shifts'] }}</strong> {{ Str::plural('shift', $summary['shifts']) }}</span>
            <span><strong>{{ intdiv($summary['minutes'], 60) }}</strong> hours scheduled</span>
            <span><strong>{{ $staffCount }}</strong> {{ Str::plural('person', $staffCount) }}</span>
            @if ($summary['onDuty'])<span class="is-on"><i></i><strong>{{ $summary['onDuty'] }}</strong> on duty now</span>@endif
            @if ($summary['missed'])<span class="is-missed"><strong>{{ $summary['missed'] }}</strong> missed</span>@endif
        </div>

        @if ($staffCount === 0)
            <div class="card text-center" style="padding: 3rem;">
                @if ($filtered)
                    <p>No one matches these filters this week.</p>
                    <a href="{{ route('ops.shifts.index', ['week' => $weekStart->toDateString()]) }}" class="btn btn-outline">Clear filters</a>
                @else
                    <p>No staff registered yet.</p>
                    @if ($canManage)<a href="{{ route('ops.staff.create') }}" class="btn btn-primary">Register staff</a>@endif
                @endif
            </div>
        @else
            {{-- Desktop / tablet: week grid --}}
            <div class="card card--flush ops-roster-wrap">
                <table class="ops-roster">
                    <thead>
                        <tr>
                            <th class="ops-roster__corner" scope="col">Team</th>
                            @foreach ($days as $day)
                                <th scope="col" @class(['is-today' => $day->isToday(), 'is-weekend' => $day->isWeekend()])>
                                    <span class="ops-roster__dow">{{ $day->format('D') }}</span>
                                    <span class="ops-roster__date">{{ $day->format('j') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach ($staffByDepartment as $department => $members)
                        <tbody>
                            <tr class="ops-roster__group">
                                <th scope="rowgroup" colspan="8"><span>{{ $department }}</span> <small>{{ $members->count() }}</small></th>
                            </tr>
                            @foreach ($members as $member)
                                @php $minutes = $minutesByStaff[$member->id] ?? 0; @endphp
                                <tr @class(['is-me' => $me?->id === $member->id, 'is-inactive' => ! $member->isActive()])>
                                    <th scope="row" class="ops-roster__person">
                                        <div class="ops-roster__person-inner">
                                            <span class="ops-avatar" aria-hidden="true">{{ Str::upper(Str::substr($member->name, 0, 1)) }}</span>
                                            <span class="ops-roster__who">
                                                @if ($canManage)<a href="{{ route('ops.staff.show', $member) }}" title="{{ $member->name }}">{{ $member->name }}</a>@else<strong title="{{ $member->name }}">{{ $member->name }}</strong>@endif
                                                <small title="{{ $member->position }}">{{ $member->position }}</small>
                                            </span>
                                            <span class="ops-roster__hours" title="Hours scheduled this week">{{ $minutes ? round($minutes / 60, 1).'h' : '—' }}</span>
                                        </div>
                                    </th>
                                    @foreach ($days as $day)
                                        @php $cell = $grid[$member->id.'|'.$day->toDateString()] ?? collect(); @endphp
                                        <td @class(['is-today' => $day->isToday(), 'is-weekend' => $day->isWeekend()])>
                                            @foreach ($cell as $shift)
                                                @php $st = $shift->displayStatus(); @endphp
                                                <{{ $canManage ? 'a' : 'div' }} @if ($canManage) href="{{ route('ops.shifts.edit', $shift) }}" @endif
                                                    class="ops-chip ops-chip--tone{{ Shift::areaTone($shift->area) }} ops-chip--{{ $st }}"
                                                    title="{{ $member->name }} · {{ $shift->starts_at->format('D j M, H:i') }}–{{ $shift->ends_at->format('H:i') }}{{ $shift->notes ? ' · '.$shift->notes : '' }}">
                                                    <span class="ops-chip__time">{{ $shift->starts_at->format('H:i') }}–{{ $shift->ends_at->format('H:i') }}@if ($shift->endsNextDay())<sup title="Ends the next day">+1</sup>@endif</span>
                                                    <span class="ops-chip__meta">
                                                        <span class="ops-chip__area">{{ $shift->area ?? 'Shift' }}</span>
                                                        @if ($statusLabel[$st] ?? null)<span class="ops-chip__status">{{ $statusLabel[$st] }}</span>@endif
                                                    </span>
                                                </{{ $canManage ? 'a' : 'div' }}>
                                            @endforeach
                                            @if ($canManage)
                                                <a href="{{ route('ops.shifts.create', ['staff' => $member, 'date' => $day->toDateString()]) }}" class="ops-cell-add @if ($cell->isEmpty()) is-empty @endif" aria-label="Add shift for {{ $member->name }} on {{ $day->format('D j M') }}">+</a>
                                            @elseif ($cell->isEmpty())
                                                <span class="ops-cell-off">Off</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                    <tfoot>
                        <tr>
                            <th scope="row">On the roster</th>
                            @foreach ($days as $day)
                                <td @class(['is-today' => $day->isToday(), 'is-weekend' => $day->isWeekend()])>{{ $headcount[$day->toDateString()] }} <small>{{ Str::plural('person', $headcount[$day->toDateString()]) }}</small></td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Phone: day-by-day list --}}
            <div class="ops-agenda">
                @foreach ($days as $day)
                    @php $dayShifts = ($byDay[$day->toDateString()] ?? collect())->sortBy('starts_at'); @endphp
                    <section @class(['ops-agenda__day', 'is-today' => $day->isToday()])>
                        <header>
                            <span class="ops-agenda__date"><strong>{{ $day->format('j') }}</strong>{{ $day->format('D') }}</span>
                            <span>{{ $day->isToday() ? 'Today' : $day->format('l') }}</span>
                            <small>{{ $headcount[$day->toDateString()] }} on</small>
                        </header>
                        @forelse ($dayShifts as $shift)
                            @php $st = $shift->displayStatus(); @endphp
                            <{{ $canManage ? 'a' : 'div' }} @if ($canManage) href="{{ route('ops.shifts.edit', $shift) }}" @endif class="ops-chip ops-chip--row ops-chip--tone{{ Shift::areaTone($shift->area) }} ops-chip--{{ $st }} @if ($me?->id === $shift->staff_member_id) is-me @endif">
                                <span class="ops-chip__time">{{ $shift->starts_at->format('H:i') }}–{{ $shift->ends_at->format('H:i') }}@if ($shift->endsNextDay())<sup>+1</sup>@endif</span>
                                <span class="ops-chip__who">{{ $shift->staffMember->name }}<small>{{ $shift->area ?? $shift->staffMember->position }}</small></span>
                                @if ($statusLabel[$st] ?? null)<span class="ops-chip__status">{{ $statusLabel[$st] }}</span>@endif
                            </{{ $canManage ? 'a' : 'div' }}>
                        @empty
                            <p class="ops-agenda__none">No shifts</p>
                        @endforelse
                    </section>
                @endforeach
            </div>

            <div class="ops-roster-legend" aria-label="Legend">
                <span class="ops-chip ops-chip--legend ops-chip--tone8 ops-chip--on"><span class="ops-chip__status">On duty</span></span>
                <span class="ops-chip ops-chip--legend ops-chip--tone8 ops-chip--completed"><span class="ops-chip__status">Done</span></span>
                <span class="ops-chip ops-chip--legend ops-chip--tone8 ops-chip--missed"><span class="ops-chip__status">Missed</span></span>
                <span class="ops-chip ops-chip--legend ops-chip--tone8 ops-chip--cancelled"><span class="ops-chip__status">Cancelled</span></span>
                <span class="text-muted">Colours show the duty area. <sup>+1</sup> ends the next day.</span>
            </div>
        @endif
    </div>
</div>
@endsection
