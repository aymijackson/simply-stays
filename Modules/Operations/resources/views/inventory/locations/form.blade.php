@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\InventoryLocation; @endphp

@section('title', $location->exists ? 'Edit location' : 'Add location')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => $location->exists ? 'Edit '.$location->label() : 'Add a location'])

        <div class="card ops-form-card">
            <form method="POST" action="{{ $location->exists ? route('ops.inventory.locations.update', $location) : route('ops.inventory.locations.store') }}">
                @csrf
                @if ($location->exists) @method('PUT') @endif

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="type">Type</label>
                        <select name="type" id="type" class="form-control" required>
                            @foreach (InventoryLocation::TYPES as $key => $label)
                                <option value="{{ $key }}" @selected(old('type', $location->type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-input name="name" label="Name" :value="$location->name" required maxlength="120" hint="e.g. Main store, Classic Suite" />
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="area">Room / area</label>
                        <input list="location-areas" name="area" id="area" class="form-control" value="{{ old('area', $location->area) }}" maxlength="60">
                        <datalist id="location-areas">
                            @foreach (InventoryLocation::AREAS as $area)<option value="{{ $area }}">@endforeach
                        </datalist>
                        <p class="form-hint">For apartments: Living room, Bedroom, Kitchen, Toilet&hellip; Leave blank for a single space.</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="listing_id">Linked listing</label>
                        <select name="listing_id" id="listing_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($listings as $listing)
                                <option value="{{ $listing->id }}" @selected((int) old('listing_id', $location->listing_id) === $listing->id)>{{ $listing->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <x-input name="sort_order" label="Sort order" type="number" min="0" :value="$location->sort_order ?? 0" />

                <div class="form-group">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes', $location->notes) }}</textarea>
                </div>

                <div class="ops-form-actions">
                    @if ($location->exists)
                        <button type="submit" form="location-delete" class="btn btn-danger-outline" onclick="return confirm('Delete this location?')">Delete</button>
                        <span style="flex:1"></span>
                    @endif
                    <a href="{{ route('ops.inventory.locations.index') }}" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
            @if ($location->exists)
                <form id="location-delete" method="POST" action="{{ route('ops.inventory.locations.destroy', $location) }}">@csrf @method('DELETE')</form>
            @endif
        </div>
    </div>
</div>
@endsection
