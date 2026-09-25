@extends(auth()->user()->portalLayout())

@section('title', 'Edit service')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('provider.services.index') }}">&larr; Back to my services</a>

        <div class="card" style="max-width: 640px; margin: 1rem auto 0;">
            <h1>Edit service</h1>

            <form method="POST" action="{{ route('provider.services.update', $service) }}">
                @csrf
                @method('PUT')
                @include('services::partials.form', ['service' => $service])
                <button type="submit" class="btn btn-primary btn-block">Save changes</button>
            </form>
        </div>
    </div>
</div>
@endsection
