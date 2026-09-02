@extends('layouts.app')

@section('title', 'Add a listing')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('host.listings.index') }}">&larr; Back to my listings</a>

        <div class="card" style="max-width: 720px; margin: 1rem auto 0;">
            <h1>Add a listing</h1>
            <p>Fill in the details below. You can publish it once you're happy with it.</p>

            <form method="POST" action="{{ route('host.listings.store') }}" enctype="multipart/form-data">
                @csrf
                @include('listings::partials.form', ['listing' => null])
                <button type="submit" class="btn btn-primary btn-block">Save listing</button>
            </form>
        </div>
    </div>
</div>
@endsection
