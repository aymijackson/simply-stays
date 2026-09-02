@php
    $event = $event ?? null;
    $recurrenceEditable = ! $event || $event->canEditRecurrence();
@endphp

<x-input name="title" label="Event title" required value="{{ old('title', $event?->title) }}" />

<div class="form-group">
    <label class="form-label" for="description">Description</label>
    <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $event?->description) }}</textarea>
</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label" for="property_id">Property (optional)</label>
        <select name="property_id" id="property_id" class="form-control">
            <option value="">No property &mdash; use address below</option>
            @foreach ($properties as $property)
                <option value="{{ $property->id }}" @selected(old('property_id', $event?->property_id) == $property->id)>{{ $property->name }}</option>
            @endforeach
        </select>
    </div>
    <x-input name="location_address" label="Location / meeting point" hint="Used when not tied to a property." value="{{ old('location_address', $event?->location_address) }}" />
</div>

<x-input name="category" label="Category" hint="e.g. Experience, Community, Party" value="{{ old('category', $event?->category) }}" />

<div class="form-group">
    <label class="form-label" for="photos">Photos</label>
    <input type="file" name="photos[]" id="photos" class="form-control" multiple accept="image/*">
</div>

<label class="radio-card" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <span>This event requires a ticket / booking</span>
    <input type="checkbox" name="is_ticketed" id="is_ticketed" value="1" data-toggle-ticketing @checked(old('is_ticketed', $event?->is_ticketed))>
</label>

<div id="ticketing-fields" style="{{ old('is_ticketed', $event?->is_ticketed) ? '' : 'display:none;' }}">
    <div class="form-row">
        <x-input name="price" type="number" step="0.01" label="Price per ticket" hint="0 = free, but still capacity-limited." value="{{ old('price', $event?->price_amount ? $event->price()->toDecimal() : 0) }}" />
        <div class="form-group">
            <label class="form-label" for="currency">Currency</label>
            <select name="currency" id="currency" class="form-control">
                @foreach (['USD', 'NGN', 'GHS', 'KES', 'ZAR', 'GBP', 'EUR'] as $code)
                    <option value="{{ $code }}" @selected(old('currency', $event?->currency ?? 'USD') === $code)>{{ $code }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<x-input name="capacity" type="number" label="Capacity per date" hint="Leave blank for unlimited." value="{{ old('capacity', $event?->capacity) }}" />

@if (auth()->user()->hasRole('Admin'))
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="commission_type">Platform commission</label>
            <select name="commission_type" id="commission_type" class="form-control">
                @foreach (['none' => 'None', 'fixed' => 'Fixed amount', 'percentage' => 'Percentage'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('commission_type', $event?->commission_type ?? 'none') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <x-input name="commission_value" type="number" step="0.01" label="Commission value" value="{{ old('commission_value', $event?->commission_value) }}" />
    </div>
@endif

<hr>

@if ($recurrenceEditable)
    <div class="form-group">
        <label class="form-label" for="recurrence_type">Repeats</label>
        <select name="recurrence_type" id="recurrence_type" class="form-control" data-toggle-recurrence>
            @foreach (['none' => 'One-off', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
                <option value="{{ $value }}" @selected(old('recurrence_type', $event?->recurrence_type ?? 'none') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @php $selectedDays = old('recurrence_days_of_week', $event?->recurrence_days_of_week ?? []); @endphp
    <div class="form-group" id="recurrence-days-field" style="{{ old('recurrence_type', $event?->recurrence_type) === 'weekly' ? '' : 'display:none;' }}">
        <label class="form-label">Repeats on</label>
        <div class="module-grid">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $i => $day)
                <label class="radio-card">
                    <input type="checkbox" name="recurrence_days_of_week[]" value="{{ $i }}" @checked(in_array($i, $selectedDays))>
                    {{ $day }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="form-row">
        <x-input name="starts_on" type="date" label="Starts on" required value="{{ old('starts_on', $event?->starts_on?->toDateString()) }}" />
        <x-input name="ends_on" type="date" label="Repeats until" hint="Optional &mdash; capped at 6 months if left blank." value="{{ old('ends_on', $event?->ends_on?->toDateString()) }}" />
    </div>

    <div class="form-row">
        <x-input name="start_time" type="time" label="Start time" required value="{{ old('start_time', $event?->start_time) }}" />
        <x-input name="end_time" type="time" label="End time" value="{{ old('end_time', $event?->end_time) }}" />
    </div>
@else
    <p class="form-hint">This event is published, so its schedule can no longer be changed here &mdash; cancel it and create a new one if the dates need to change.</p>
    <p><strong>{{ $event->recurrenceSummary() }}</strong>, starting {{ $event->starts_on->format('M j, Y') }}</p>

    <input type="hidden" name="recurrence_type" value="{{ $event->recurrence_type }}">
    <input type="hidden" name="starts_on" value="{{ $event->starts_on->toDateString() }}">
    <input type="hidden" name="ends_on" value="{{ $event->ends_on?->toDateString() }}">
    <input type="hidden" name="start_time" value="{{ $event->start_time }}">
    <input type="hidden" name="end_time" value="{{ $event->end_time }}">
    @foreach ($event->recurrence_days_of_week ?? [] as $day)
        <input type="hidden" name="recurrence_days_of_week[]" value="{{ $day }}">
    @endforeach
@endif

@if ($event?->status === 'rejected')
    <x-alert type="danger">Rejected: {{ $event->rejection_reason }}. Saving changes will resubmit this event for approval.</x-alert>
@endif

@push('scripts')
    <script>
        $(function () {
            $('[data-toggle-ticketing]').on('change', function () {
                $('#ticketing-fields').toggle($(this).is(':checked'));
            });

            $('[data-toggle-recurrence]').on('change', function () {
                $('#recurrence-days-field').toggle($(this).val() === 'weekly');
            });
        });
    </script>
@endpush
