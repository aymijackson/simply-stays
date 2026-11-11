{{--
    Editable form body. Expects: $data (array), $asStaff (bool).
    Staff-only fields are hidden from guests while blank, and shown read-only once staff fill them.
--}}
@foreach (\Modules\Bookings\Support\GuestRegistrationForm::sections() as $sectionKey => $section)
    @php
        $sectionStaff = $section['staff'] ?? false;
        $visibleFields = collect($section['fields'])->filter(function ($field, $key) use ($asStaff, $sectionStaff, $data) {
            $staffField = $sectionStaff || ($field['staff'] ?? false);
            return $asStaff || ! $staffField || filled($data[$key] ?? null);
        });
    @endphp

    @continue($visibleFields->isEmpty())

    <section class="gform-section">
        <h3 class="gform-section__title">
            {{ $section['title'] }}
            @if ($sectionStaff)
                <span class="gform-section__tag">Staff</span>
            @endif
        </h3>

        @if ($sectionKey === 'acknowledgement')
            <p class="gform-notice">{{ $section['notice'] }}</p>
        @endif

        <div class="gform-grid">
            @foreach ($visibleFields as $key => $field)
                @php
                    $type = $field['type'] ?? 'text';
                    $readOnly = ($sectionStaff || ($field['staff'] ?? false)) && ! $asStaff;
                    $value = old("data.$key", $data[$key] ?? null);
                    $id = 'gf-'.$key;
                @endphp
                <div class="form-group {{ $type === 'textarea' ? 'gform-grid__wide' : '' }}">
                    <label class="form-label" for="{{ $id }}">
                        {{ $field['label'] }}
                        @if ($field['required'] ?? false) <span style="color: var(--color-danger);">*</span> @endif
                    </label>

                    @if ($readOnly)
                        <div class="gform-readonly">{{ $value ?: '—' }}</div>
                    @elseif ($type === 'textarea')
                        <textarea name="data[{{ $key }}]" id="{{ $id }}" class="form-control" rows="2">{{ $value }}</textarea>
                    @elseif ($type === 'select')
                        <select name="data[{{ $key }}]" id="{{ $id }}" class="form-control">
                            <option value="">—</option>
                            @foreach ($field['options'] as $option)
                                <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="{{ $type }}" name="data[{{ $key }}]" id="{{ $id }}" class="form-control"
                               value="{{ $type === 'datetime-local' && $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i') : $value }}"
                               @if ($type === 'number') min="0" @endif
                               @if ($field['required'] ?? false) required @endif>
                    @endif

                    @error("data.$key")
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        @if ($sectionKey === 'vehicle')
            <p class="gform-notice"><strong>Parking notice:</strong> {{ $section['notice'] }}</p>
        @endif
    </section>

    @if ($sectionKey === 'acknowledgement')
        <section class="gform-section">
            <h3 class="gform-section__title">Guest's signature</h3>
            @include('bookings::guest-forms.partials.signature', ['existing' => $existingSignature ?? null, 'required' => ! $asStaff && empty($existingSignature)])

            <label style="display: flex; gap: 0.5rem; align-items: flex-start; margin-top: 1rem;">
                <input type="checkbox" name="acknowledged" value="1" @checked(old('acknowledged', ! empty($existingSignature))) @if (! $asStaff) required @endif style="margin-top: 0.25rem;">
                <span>I have read and agree to the guest acknowledgement above.</span>
            </label>
            @error('acknowledged')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </section>
    @endif
@endforeach

<p class="gform-notice gform-notice--privacy"><strong>Privacy note:</strong> {{ \Modules\Bookings\Support\GuestRegistrationForm::PRIVACY_NOTE }}</p>
