@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\Shift; @endphp

@section('title', 'Attendance')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Attendance'])

        <div class="ops-toolbar">
            <form method="GET" class="gform-filters">
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="From">
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="To">
                <button class="btn btn-outline">Show</button>
            </form>
        </div>

        <div class="card card--flush ops-table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Staff</th><th class="ops-num">Shifts</th><th class="ops-num">Worked</th><th class="ops-num">Missed</th><th class="ops-num">Late (10m+)</th><th class="ops-num">Scheduled hrs</th><th class="ops-num">Worked hrs</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><a href="{{ route('ops.staff.show', $row['member']) }}">{{ $row['member']->name }}</a> <span class="text-muted">{{ $row['member']->position }}</span></td>
                            <td class="ops-num">{{ $row['scheduled'] }}</td>
                            <td class="ops-num">{{ $row['worked'] }}</td>
                            <td class="ops-num {{ $row['missed'] ? 'ops-neg' : '' }}">{{ $row['missed'] }}</td>
                            <td class="ops-num">{{ $row['late'] }}</td>
                            <td class="ops-num">{{ Shift::formatMinutes($row['scheduled_minutes']) }}</td>
                            <td class="ops-num"><strong>{{ Shift::formatMinutes($row['worked_minutes']) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No shifts in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($handovers->isNotEmpty())
            <div class="card ops-card" style="margin-top:1.25rem;">
                <h3 class="ops-card__title">Handover notes</h3>
                @foreach ($handovers as $shift)
                    <div class="ops-row">
                        <div><strong>{{ $shift->staffMember->name }}</strong> <span class="text-muted">{{ $shift->starts_at->format('D j M') }}</span><div>{{ $shift->handover_notes }}</div></div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
