@extends('layouts.app')

@section('title', 'Activity log')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('admin.index') }}">&larr; Back to overview</a>

        <h1 style="margin-top: 1rem;">Activity log</h1>

        @if ($activities->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin: 0;">No activity recorded yet.</p>
            </div>
        @else
            <div class="card" style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Event</th>
                            <th>Subject</th>
                            <th>Caused by</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activities as $activity)
                            <tr>
                                <td style="white-space: nowrap;">{{ $activity->created_at->format('M j, H:i') }}</td>
                                <td>{{ $activity->description }}</td>
                                <td>{{ $activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : '&mdash;' }}</td>
                                <td>
                                    @if ($activity->causer)
                                        <span style="display: flex; align-items: center; gap: 0.4rem;">
                                            <span class="avatar" style="width:22px;height:22px;font-size:0.7rem;">{{ strtoupper(substr($activity->causer->name, 0, 1)) }}</span>
                                            {{ $activity->causer->name }}
                                        </span>
                                    @else
                                        System
                                    @endif
                                </td>
                                <td style="font-size: 0.85rem; color: var(--color-text-muted);">
                                    @if ($activity->properties->isNotEmpty())
                                        {{ $activity->properties->toJson() }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 2.5rem;">
                {{ $activities->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
