@extends('layouts.app')

@section('title', 'Edit property')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('host.properties.index') }}">&larr; Back to my properties</a>

        <div class="card" style="max-width: 720px; margin: 1rem auto 0;">
            <h1>Edit property</h1>

            <form method="POST" action="{{ route('host.properties.update', $property) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('listings::host.properties.partials.form', ['property' => $property])
                <button type="submit" class="btn btn-primary btn-block">Save changes</button>
            </form>
        </div>
    </div>
</div>
@endsection
