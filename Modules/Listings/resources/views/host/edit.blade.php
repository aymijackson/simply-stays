@extends(auth()->user()->portalLayout())

@section('title', 'Edit listing')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('host.listings.index') }}">&larr; Back to my listings</a>

        <div class="card" style="max-width: 720px; margin: 1rem auto 0;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1 style="margin: 0;">Edit listing</h1>
                <a href="{{ route('host.listings.complimentary.index', $listing) }}" class="btn btn-outline btn-sm">
                    <x-icon name="gift" style="width:15px;height:15px;" /> Complimentary offers
                </a>
            </div>

            <form method="POST" action="{{ route('host.listings.update', $listing) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('listings::partials.form', ['listing' => $listing, 'selectedAmenityIds' => $selectedAmenityIds])
                <button type="submit" class="btn btn-primary btn-block">Save changes</button>
            </form>

            @foreach ($listing->getMedia('photos') as $media)
                <form id="photo-delete-{{ $media->id }}" method="POST" action="{{ route('host.listings.photos.destroy', [$listing, $media]) }}" onsubmit="return confirm('Remove this photo?');" hidden>
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        </div>
    </div>
</div>
@endsection
