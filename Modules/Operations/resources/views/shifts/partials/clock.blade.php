{{-- One of "my shifts" with its clock-in / clock-out control. --}}
<div class="ops-row ops-row--clock">
    <div>
        <strong>{{ $shift->starts_at->format('D j M') }}</strong>
        &middot; {{ $shift->starts_at->format('g:i A') }} – {{ $shift->ends_at->format('g:i A') }}
        @if ($shift->area) <span class="text-muted">&middot; {{ $shift->area }}</span>@endif
        @if ($shift->notes)<div class="form-hint" style="margin:0;">{{ $shift->notes }}</div>@endif
        @if ($shift->clock_in_at)
            <div class="form-hint" style="margin:0;">In {{ $shift->clock_in_at->format('g:i A') }}@if ($shift->clock_out_at) · out {{ $shift->clock_out_at->format('g:i A') }}@endif · {{ \Modules\Operations\Models\Shift::formatMinutes($shift->workedMinutes()) }}</div>
        @endif
    </div>
    <div>
        @if ($shift->canClockIn())
            <form method="POST" action="{{ route('ops.shifts.clock-in', $shift) }}">
                @csrf
                <button class="btn btn-primary btn-sm">Clock in</button>
            </form>
        @elseif ($shift->isOnDuty())
            <form method="POST" action="{{ route('ops.shifts.clock-out', $shift) }}" class="ops-clockout">
                @csrf
                <input type="text" name="handover_notes" class="form-control" placeholder="Handover notes (optional)" maxlength="2000">
                <button class="btn btn-primary btn-sm">Clock out</button>
            </form>
        @else
            @include('operations::shifts.partials.status', ['shift' => $shift])
        @endif
    </div>
</div>
