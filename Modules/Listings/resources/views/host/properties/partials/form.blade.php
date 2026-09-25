@php
    $property = $property ?? null;
    $hostUsers = $hostUsers ?? null;
@endphp

@if ($hostUsers)
    <div class="form-group">
        <label class="form-label" for="host_id">Hosted by</label>
        <select name="host_id" id="host_id" class="form-control">
            @foreach ($hostUsers as $hostUser)
                <option value="{{ $hostUser->id }}" @selected(old('host_id', $property?->host_id) === $hostUser->id)>{{ $hostUser->name }} ({{ $hostUser->email }})</option>
            @endforeach
        </select>
        <p class="form-hint">Admin only: reassigns this property (and its listings) to a different host.</p>
        @error('host_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
@endif

<x-input name="name" label="Property name" required value="{{ old('name', $property?->name) }}" placeholder="e.g. Luxurious Inn Stay" />

<div class="form-group">
    <label class="form-label" for="description">Description</label>
    <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $property?->description) }}</textarea>
    @error('description')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-group">
    <label class="form-label" for="property_type">Property type</label>
    <select name="property_type" id="property_type" class="form-control">
        @foreach (['apartment' => 'Apartment', 'house' => 'House', 'villa' => 'Villa', 'studio' => 'Studio', 'room' => 'Private room'] as $value => $label)
            <option value="{{ $value }}" @selected(old('property_type', $property?->property_type) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('property_type')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<x-input name="address" label="Street address" required value="{{ old('address', $property?->address) }}" />

<div class="form-row">
    <x-input name="city" label="City" required value="{{ old('city', $property?->city) }}" />
    <x-input name="country" label="Country" required value="{{ old('country', $property?->country) }}" />
</div>

<x-map-picker :latitude="$property?->latitude" :longitude="$property?->longitude" />

<div class="form-row">
    <x-input name="latitude" type="number" step="any" label="Latitude" value="{{ old('latitude', $property?->latitude) }}" />
    <x-input name="longitude" type="number" step="any" label="Longitude" value="{{ old('longitude', $property?->longitude) }}" />
</div>

<div class="form-row">
    <x-input name="service_charge_percent" type="number" step="0.01" label="Service charge %" hint="Applied to bookings and food orders at this property." value="{{ old('service_charge_percent', $property?->service_charge_percent ?? 0) }}" />
    <x-input name="tax_percent" type="number" step="0.01" label="Tax %" value="{{ old('tax_percent', $property?->tax_percent ?? 0) }}" />
</div>

<div class="form-group">
    <label class="form-label" for="photos">Property photos</label>
    <input type="file" name="photos[]" id="photos" class="form-control" multiple accept="image/*">
    <p class="form-hint">Building exterior, common areas, etc. JPG/PNG, up to 5MB each.</p>
    @error('photos.*')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

@if ($property && $property->photoUrls()->isNotEmpty())
    <div class="form-group">
        <label class="form-label">Current photos</label>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem;">
            @foreach ($property->getMedia('photos') as $media)
                <div>
                    <img src="{{ $media->getUrl() }}" alt="" style="width: 100%; height: 100px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 0.35rem;">
                    <form method="POST" action="{{ route('host.properties.photos.destroy', [$property, $media]) }}" onsubmit="return confirm('Remove this photo?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline btn-block" style="color: var(--color-danger); border-color: var(--color-danger); padding: 0.3rem;">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif
