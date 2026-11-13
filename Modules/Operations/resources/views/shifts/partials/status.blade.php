@php
    $label = match (true) {
        $shift->isOnDuty() => 'On duty',
        $shift->status === 'completed' => 'Done',
        $shift->status === 'missed' => 'Missed',
        $shift->status === 'cancelled' => 'Cancelled',
        default => 'Scheduled',
    };
    $class = match ($label) {
        'On duty', 'Done' => 'badge--success',
        'Missed' => 'badge--danger',
        'Cancelled' => 'badge--dark',
        default => '',
    };
@endphp
<span class="badge {{ $class }}">{{ $label }}@if ($shift->lateMinutes() > 10) &middot; {{ $shift->lateMinutes() }}m late @endif</span>
