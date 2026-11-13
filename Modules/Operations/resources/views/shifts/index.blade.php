@extends(auth()->user()->portalLayout())

@section('title', 'Shifts')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Shift roster'])

        <div class="ops-toolbar">
            <div class="ops-week-nav">
                <a href="{{ route('ops.shifts.index', ['week' => $weekStart->subWeek()->toDateString()]) }}" class="btn btn-ghost btn-sm" aria-label="Previous week"><x-icon name="chevron-left" /></a>
                <strong>{{ $weekStart->format('j M') }} – {{ $weekStart->endOfWeek()->format('j M Y') }}</strong>
                <a href="{{ route('ops.shifts.index', ['week' => $weekStart->addWeek()->toDateString()]) }}" class="btn btn-ghost btn-sm" aria-label="Next week"><x-icon name="chevron-right" /></a>
                @unless ($weekStart->isSameDay(now()->startOfWeek()))
                    <a href="{{ route('ops.shifts.index') }}" class="btn btn-ghost btn-sm">This week</a>
                @endunless
            </div>
            @if ($canManage)
                <div class="ops-toolbar__actions">
                    <a href="{{ route('ops.shifts.attendance') }}" class="btn btn-outline">Attendance</a>
                    <a href="{{ route('ops.shifts.create') }}" class="btn btn-primary">Schedule shift</a>
                </div>
            @endif
        </div>

        @if ($staff->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p>No staff registered yet.</p>
                @if ($canManage)<a href="{{ route('ops.staff.create') }}" class="btn btn-primary">Register staff</a>@endif
            </div>
        @else
            <div class="card card--flush ops-table-wrap">
                <table class="ops-roster">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            @foreach ($days as $day)
                                <th @class(['is-today' => $day->isToday()])>{{ $day->format('D') }}<span>{{ $day->format('j M') }}</span></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $member)
                            <tr @class(['is-me' => $me?->id === $member->id])>
                                <th>
                                    @if ($canManage)<a href="{{ route('ops.staff.show', $member) }}">{{ $member->name }}</a>@else{{ $member->name }}@endif
                                    <span>{{ $member->position }}</span>
                                </th>
                                @foreach ($days as $day)
                                    <td @class(['is-today' => $day->isToday()])>
                                        @foreach ($grid[$member->id.'|'.$day->toDateString()] ?? [] as $shift)
                                            @php $tag = $canManage ? 'a' : 'div'; @endphp
                                            <{{ $tag }} @if ($canManage) href="{{ route('ops.shifts.edit', $shift) }}" @endif class="ops-shift ops-shift--{{ $shift->isOnDuty() ? 'on' : $shift->status }}" title="{{ $shift->notes }}">
                                                {{ $shift->starts_at->format('H:i') }}–{{ $shift->ends_at->format('H:i') }}
                                                @if ($shift->area)<small>{{ $shift->area }}</small>@endif
                                            </{{ $tag }}>
                                        @endforeach
                                        @if ($canManage)
                                            <a href="{{ route('ops.shifts.create', ['staff' => $member->id, 'date' => $day->toDateString()]) }}" class="ops-shift-add" aria-label="Add shift for {{ $member->name }} on {{ $day->format('D j M') }}">+</a>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="form-hint ops-legend">
                <span class="ops-shift ops-shift--scheduled">Scheduled</span>
                <span class="ops-shift ops-shift--on">On duty</span>
                <span class="ops-shift ops-shift--completed">Completed</span>
                <span class="ops-shift ops-shift--missed">Missed</span>
                <span class="ops-shift ops-shift--cancelled">Cancelled</span>
            </p>
        @endif
    </div>
</div>
@endsection
