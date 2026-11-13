@extends(auth()->user()->portalLayout())

@section('title', 'Add a service')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('provider.services.index') }}">&larr; Back to {{ auth()->user()->hasRole('Admin') ? 'all services' : 'my services' }}</a>

        <div class="card" style="max-width: 640px; margin: 1rem auto 0;">
            <h1>Add a service</h1>

            <form method="POST" action="{{ route('provider.services.store') }}">
                @csrf
                @include('services::partials.form', ['service' => null])
                <button type="submit" class="btn btn-primary btn-block">Save service</button>
            </form>
        </div>
    </div>
</div>
@endsection
