@extends('layouts.app')

@section('title', 'Add an event')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('events.manage.index') }}">&larr; Back to my events</a>

        <div class="card" style="max-width: 720px; margin: 1rem auto 0;">
            <h1>Add an event</h1>
            <p class="form-hint">
                @if (auth()->user()->hasRole('Admin'))
                    Admin-created events publish immediately.
                @else
                    Your event will be reviewed by an admin before it appears publicly.
                @endif
            </p>

            @if ($errors->any())
                <x-alert type="danger">{{ $errors->first() }}</x-alert>
            @endif

            <form method="POST" action="{{ route('events.manage.store') }}" enctype="multipart/form-data">
                @csrf
                @include('events::partials.form', ['event' => null])
                <button type="submit" class="btn btn-primary btn-block">Save event</button>
            </form>
        </div>
    </div>
</div>
@endsection
