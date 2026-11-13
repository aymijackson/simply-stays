@extends(auth()->user()->portalLayout())

@section('title', 'Edit service')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('provider.services.index') }}">&larr; Back to {{ auth()->user()->hasRole('Admin') ? 'all services' : 'my services' }}</a>

        <div class="card" style="max-width: 640px; margin: 1rem auto 0;">
            <h1>Edit service</h1>

            <form method="POST" action="{{ route('provider.services.update', $service) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('services::partials.form', ['service' => $service])
                <button type="submit" class="btn btn-primary btn-block">Save changes</button>
            </form>

            {{-- Photo actions live outside the edit form: a nested form would submit to the wrong place. --}}
            @foreach ($service->getMedia('photos') as $media)
                <form id="photo-delete-{{ $media->uuid }}" method="POST" action="{{ route('provider.services.photos.destroy', [$service, $media]) }}" onsubmit="return confirm('Remove this photo?');" hidden>@csrf @method('DELETE')</form>
                <form id="photo-cover-{{ $media->uuid }}" method="POST" action="{{ route('provider.services.photos.cover', [$service, $media]) }}" hidden>@csrf @method('PATCH')</form>
            @endforeach
        </div>
    </div>
</div>
@endsection
