@extends(auth()->user()->portalLayout())

@section('title', 'Edit event')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('events.manage.index') }}">&larr; Back to my events</a>

        <div class="card" style="max-width: 720px; margin: 1rem auto 0;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1 style="margin: 0;">Edit event</h1>
                <x-status-badge :status="$event->status" />
            </div>

            @if ($errors->any())
                <x-alert type="danger">{{ $errors->first() }}</x-alert>
            @endif

            <form method="POST" action="{{ route('events.manage.update', $event) }}" enctype="multipart/form-data" style="margin-top: 1rem;">
                @csrf
                @method('PUT')
                @include('events::partials.form', ['event' => $event])
                <button type="submit" class="btn btn-primary btn-block">Save changes</button>
            </form>

            @if (in_array($event->status, ['draft', 'pending_approval', 'published', 'rejected']))
                <form method="POST" action="{{ route('events.manage.cancel', $event) }}" onsubmit="return confirm('Cancel this event? Guests will no longer be able to see or book it.');" style="margin-top: 1rem;">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-danger-outline btn-block">Cancel event</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
