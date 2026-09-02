@extends('layouts.app')

@section('title', 'Events calendar')

@section('content')
<div class="page page--tight">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
            <h1 style="margin: 0;">Events calendar</h1>
            <a href="{{ route('events.index') }}" class="btn btn-outline btn-sm">
                <x-icon name="check" style="width:15px;height:15px;" /> List view
            </a>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <a href="{{ route('events.calendar', ['month' => $prevMonth]) }}" class="btn btn-outline btn-sm">&larr; Prev</a>
            <h2 style="margin: 0;">{{ $month->format('F Y') }}</h2>
            <a href="{{ route('events.calendar', ['month' => $nextMonth]) }}" class="btn btn-outline btn-sm">Next &rarr;</a>
        </div>

        <div class="card" style="overflow-x: auto; padding: 0;">
            <table class="calendar-grid">
                <thead>
                    <tr>
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                            <th>{{ $day }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weeks as $week)
                        <tr>
                            @foreach ($week as $day)
                                <td class="calendar-grid__cell {{ $day['inMonth'] ? '' : 'calendar-grid__cell--muted' }} {{ $day['date']->isToday() ? 'calendar-grid__cell--today' : '' }}">
                                    <div class="calendar-grid__date">{{ $day['date']->day }}</div>
                                    @foreach ($day['occurrences']->take(3) as $occurrence)
                                        <a href="{{ route('events.show', $occurrence->event) }}" class="calendar-grid__pill">{{ $occurrence->event->title }}</a>
                                    @endforeach
                                    @if ($day['occurrences']->count() > 3)
                                        <span class="form-hint">+{{ $day['occurrences']->count() - 3 }} more</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
