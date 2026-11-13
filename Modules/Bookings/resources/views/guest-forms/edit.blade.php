@extends(auth()->user()->portalLayout())

@section('title', 'Edit guest registration')

@section('content')
<div class="page page--tight">
    <div class="container" style="max-width: 900px;">
        <a href="{{ route('guest-forms.show', $registration) }}">&larr; Back to form {{ $registration->referenceCode() }}</a>

        <form method="POST" action="{{ route('guest-forms.update', $registration) }}" class="card gform" style="margin-top: 1rem;">
            @csrf
            @method('PUT')

            @include('bookings::guest-forms.partials.letterhead', ['property' => $property, 'settings' => $settings])
            @include('bookings::guest-forms.partials.fields', ['data' => $data, 'asStaff' => $asStaff, 'existingSignature' => $registration->signature])

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1.5rem;">Save changes</button>
        </form>
    </div>
</div>
@endsection
