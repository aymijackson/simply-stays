@extends(auth()->user()->portalLayout())

@section('title', 'Add a property')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('host.properties.index') }}">&larr; Back to my properties</a>

        <div class="card" style="max-width: 720px; margin: 1rem auto 0;">
            <h1>Add a property</h1>
            <p>This is the building or compound &mdash; e.g. "Luxurious Inn Stay". You'll add individual unit types (3 bedroom flat, 2 bedroom flat, etc.) to it afterwards.</p>

            <form method="POST" action="{{ route('host.properties.store') }}" enctype="multipart/form-data">
                @csrf
                @include('listings::host.properties.partials.form', ['property' => null])
                <button type="submit" class="btn btn-primary btn-block">Save property</button>
            </form>
        </div>
    </div>
</div>
@endsection
