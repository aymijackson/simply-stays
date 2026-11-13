@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\Shift; use Modules\Operations\Http\Controllers\ShiftController; @endphp

@section('title', $shift->exists ? 'Edit shift' : 'Schedule shift')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => $shift->exists ? 'Edit shift' : 'Schedule a shift'])

        <div class="card ops-form-card">
            <form method="POST" action="{{ $shift->exists ? route('ops.shifts.update', $shift) : route('ops.shifts.store') }}">
                @csrf
                @if ($shift->exists) @method('PUT') @endif

                <div class="form-group">
                    <label class="form-label" for="staff_member_id">Staff member</label>
                    <select name="staff_member_id" id="staff_member_id" class="form-control" required>
                        <option value="">Choose…</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}" @selected((int) old('staff_member_id', $shift->staff_member_id) === $member->id)>{{ $member->name }} — {{ $member->position }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-row">
                    <x-input name="date" label="Date" type="date" :value="$shift->starts_at?->toDateString()" required />
                    <x-input name="start_time" label="Starts" type="time" :value="$shift->starts_at?->format('H:i')" required />
                    <x-input name="end_time" label="Ends" type="time" :value="$shift->ends_at?->format('H:i')" required hint="Earlier than the start = overnight." />
                </div>

                <div class="ops-presets" data-shift-presets>
                    <span class="form-hint" style="margin:0;">Quick:</span>
                    <button type="button" class="btn btn-ghost btn-sm" data-start="07:00" data-end="15:00">Morning 7–3</button>
                    <button type="button" class="btn btn-ghost btn-sm" data-start="15:00" data-end="23:00">Afternoon 3–11</button>
                    <button type="button" class="btn btn-ghost btn-sm" data-start="19:00" data-end="07:00">Night 7pm–7am</button>
                    <button type="button" class="btn btn-ghost btn-sm" data-start="08:00" data-end="17:00">Day 8–5</button>
                </div>

                <div class="form-group">
                    <label class="form-label" for="area">Duty / area</label>
                    <input list="shift-areas" name="area" id="area" class="form-control" value="{{ old('area', $shift->area) }}" maxlength="60">
                    <datalist id="shift-areas">@foreach (ShiftController::AREAS as $area)<option value="{{ $area }}">@endforeach</datalist>
                </div>

                <div class="form-group">
                    <label class="form-label" for="notes">Instructions</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2" maxlength="1000">{{ old('notes', $shift->notes) }}</textarea>
                </div>

                @if (! $shift->exists)
                    <fieldset class="ops-fieldset">
                        <legend>Repeat</legend>
                        <div class="form-row">
                            <x-input name="repeat_days" label="Also schedule the next … days" type="number" min="0" max="27" :value="0" />
                            <label class="ops-check" style="align-self:center;">
                                <input type="checkbox" name="skip_weekends" value="1" @checked(old('skip_weekends'))> Skip Saturdays &amp; Sundays
                            </label>
                        </div>
                    </fieldset>
                @else
                    <fieldset class="ops-fieldset">
                        <legend>Attendance</legend>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    @foreach (Shift::STATUSES as $status)
                                        <option value="{{ $status }}" @selected(old('status', $shift->status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-input name="clock_in_at" label="Clocked in" type="datetime-local" :value="$shift->clock_in_at?->format('Y-m-d\TH:i')" />
                            <x-input name="clock_out_at" label="Clocked out" type="datetime-local" :value="$shift->clock_out_at?->format('Y-m-d\TH:i')" />
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="handover_notes">Handover notes</label>
                            <textarea name="handover_notes" id="handover_notes" class="form-control" rows="2">{{ old('handover_notes', $shift->handover_notes) }}</textarea>
                        </div>
                    </fieldset>
                @endif

                <div class="ops-form-actions">
                    @if ($shift->exists)
                        <button type="submit" form="shift-delete" class="btn btn-danger-outline" onclick="return confirm('Remove this shift?')">Remove</button>
                        <span style="flex:1"></span>
                    @endif
                    <a href="{{ route('ops.shifts.index') }}" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
            @if ($shift->exists)
                <form id="shift-delete" method="POST" action="{{ route('ops.shifts.destroy', $shift) }}">@csrf @method('DELETE')</form>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('[data-shift-presets]').on('click', 'button', function () {
        $('#start_time').val($(this).data('start'));
        $('#end_time').val($(this).data('end'));
    });
});
</script>
@endpush
