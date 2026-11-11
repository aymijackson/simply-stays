@extends(auth()->user()->portalLayout())

@section('title', 'Guest registration '.$registration->reference())

@section('content')
@php $form = \Modules\Bookings\Support\GuestRegistrationForm::class; @endphp
<div class="page page--tight">
    <div class="container" style="max-width: 900px;">
        <div class="gform-toolbar no-print">
            <a href="{{ route('guest-forms.index') }}">&larr; Back to guest forms</a>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">Print</button>
                <a href="{{ route('guest-forms.edit', $registration) }}" class="btn btn-primary btn-sm">{{ $registration->signature ? 'Edit' : 'Edit & sign' }}</a>
                @if ($asStaff)
                    <form method="POST" action="{{ route('guest-forms.destroy', $registration) }}" onsubmit="return confirm('Delete this registration form and its uploads?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger-outline btn-sm">Delete</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card gform gform--print">
            <div class="gform-meta">
                <span><strong>{{ $registration->reference() }}</strong></span>
                @if ($registration->booking)
                    <span>Booking <a href="{{ route('bookings.show', $registration->booking) }}">#{{ $registration->booking_id }}</a></span>
                @endif
                <span>Submitted {{ $registration->created_at->format('M j, Y g:ia') }}@if ($registration->creator) by {{ $registration->creator->name }}@endif</span>
            </div>

            @include('bookings::guest-forms.partials.letterhead', ['property' => $registration->property, 'settings' => $settings])

            @foreach ($form::sections() as $sectionKey => $section)
                @php
                    $sectionStaff = $section['staff'] ?? false;
                    $fields = collect($section['fields'])->filter(fn ($field, $key) => $asStaff || ! ($sectionStaff || ($field['staff'] ?? false)) || $registration->value($key) !== null);
                @endphp
                @continue($fields->isEmpty())

                <section class="gform-section">
                    <h3 class="gform-section__title">{{ $section['title'] }}</h3>

                    @if ($sectionKey === 'acknowledgement')
                        <p class="gform-notice">{{ $section['notice'] }}</p>
                    @endif

                    <dl class="gform-grid gform-values">
                        @foreach ($fields as $key => $field)
                            @php
                                $value = $registration->value($key);
                                if ($value && ($field['type'] ?? null) === 'date') { $value = \Illuminate\Support\Carbon::parse($value)->format('D, M j, Y'); }
                                if ($value && ($field['type'] ?? null) === 'datetime-local') { $value = \Illuminate\Support\Carbon::parse($value)->format('M j, Y g:ia'); }
                            @endphp
                            <div class="{{ ($field['type'] ?? null) === 'textarea' ? 'gform-grid__wide' : '' }}">
                                <dt>{{ $field['label'] }}</dt>
                                <dd>{!! $value !== null ? nl2br(e($value)) : '&nbsp;' !!}</dd>
                            </div>
                        @endforeach

                        @if ($sectionKey === 'acknowledgement')
                            <div class="gform-grid__wide">
                                <dt>Guest's signature</dt>
                                <dd class="gform-signature">
                                    @if ($registration->signature)
                                        <img src="{{ $registration->signature }}" alt="Guest signature">
                                        <span class="form-hint">Signed {{ $registration->signed_at?->format('M j, Y g:ia') }}</span>
                                    @else
                                        <span class="form-hint no-print">Not signed yet.</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @if ($sectionKey === 'vehicle')
                        <p class="gform-notice"><strong>Parking notice:</strong> {{ $section['notice'] }}</p>
                    @endif
                </section>
            @endforeach

            <p class="gform-notice gform-notice--privacy"><strong>Privacy note:</strong> {{ $form::PRIVACY_NOTE }}</p>
        </div>

        <div class="card no-print" style="margin-top: 1.5rem;">
            <h2 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem;"><x-icon name="image" /> Hand-filled form</h2>
            <p class="form-hint" style="margin-top: 0;">Scans or photos of the signed paper form. Stored privately &mdash; only staff and this guest can open them.</p>

            @if ($scans->isNotEmpty())
                <div class="gform-scans">
                    @foreach ($scans as $scan)
                        <div class="gform-scan">
                            <a href="{{ route('guest-forms.scans.show', [$registration, $scan]) }}" target="_blank" rel="noopener" class="gform-scan__preview">
                                @if (str_starts_with($scan->mime_type, 'image/'))
                                    <img src="{{ route('guest-forms.scans.show', [$registration, $scan]) }}" alt="{{ $scan->file_name }}" loading="lazy">
                                @else
                                    <span class="gform-scan__pdf">PDF</span>
                                @endif
                            </a>
                            <div class="gform-scan__name" title="{{ $scan->file_name }}">{{ $scan->file_name }}</div>
                            <div class="form-hint" style="margin: 0;">{{ $scan->created_at->format('M j, Y') }} &middot; {{ $scan->human_readable_size }}</div>
                            @if ($asStaff)
                                <form method="POST" action="{{ route('guest-forms.scans.destroy', [$registration, $scan]) }}" onsubmit="return confirm('Remove this upload?');" style="margin-top: 0.35rem;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger-outline btn-sm btn-block">Remove</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @elseif (! $asStaff)
                <p style="margin-bottom: 0;">No hand-filled form uploaded.</p>
            @endif

            @if ($asStaff)
                <form method="POST" action="{{ route('guest-forms.scans.store', $registration) }}" enctype="multipart/form-data" style="margin-top: 1rem;">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="scans">Upload scans or photos</label>
                        <input type="file" name="scans[]" id="scans" class="form-control" multiple accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        <p class="form-hint">JPG, PNG, WebP or PDF, up to 10MB each. Up to 10 files at once.</p>
                        @error('scans')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                        @error('scans.*')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
