@extends(auth()->user()->portalLayout())

@section('title', 'Add item')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Add an item'])

        <div class="card ops-form-card">
            <form method="POST" action="{{ route('ops.inventory.items.store') }}">
                @csrf
                @include('operations::inventory.items._form')

                <fieldset class="ops-fieldset">
                    <legend>Opening stock (optional)</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="opening_location_id">Where is it now?</label>
                            <select name="opening_location_id" id="opening_location_id" class="form-control">
                                <option value="">—</option>
                                @foreach ($locations as $loc)
                                    <option value="{{ $loc->id }}" @selected((int) old('opening_location_id') === $loc->id)>{{ $loc->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-input name="opening_quantity" label="How many?" type="number" min="0" />
                    </div>
                </fieldset>

                <div class="ops-form-actions">
                    <a href="{{ route('ops.inventory.index') }}" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary">Save item</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
