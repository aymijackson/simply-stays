@php
    $listing = $listing ?? null;
    $selectedAmenityIds = $selectedAmenityIds ?? [];
@endphp

@if ($properties->isEmpty())
    <x-alert type="danger">
        You need a property before you can add a listing. <a href="{{ route('host.properties.create') }}">Create one first</a>.
    </x-alert>
@else
    <div class="form-group">
        <label class="form-label" for="property_id">Property</label>
        <select name="property_id" id="property_id" class="form-control">
            @foreach ($properties as $property)
                <option value="{{ $property->id }}" @selected(old('property_id', $listing?->property_id) == $property->id)>{{ $property->name }} &mdash; {{ $property->city }}, {{ $property->country }}</option>
            @endforeach
        </select>
        @error('property_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
@endif

<x-input name="title" label="Listing title" required value="{{ old('title', $listing?->title) }}" placeholder="e.g. 3 Bedroom Flat" />

<div class="form-group">
    <label class="form-label" for="description">Description</label>
    <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $listing?->description) }}</textarea>
    @error('description')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-row">
    <x-input name="price" type="number" label="Price per night" required value="{{ old('price', $listing?->base_price_amount ? $listing->price()->toDecimal() : '') }}" />
    <div class="form-group">
        <label class="form-label" for="currency">Currency</label>
        <select name="currency" id="currency" class="form-control">
            @foreach (['USD', 'NGN', 'GHS', 'KES', 'ZAR', 'GBP', 'EUR'] as $code)
                <option value="{{ $code }}" @selected(old('currency', $listing?->currency ?? 'USD') === $code)>{{ $code }}</option>
            @endforeach
        </select>
        @error('currency')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="form-row">
    <x-input name="max_guests" type="number" label="Max guests" required value="{{ old('max_guests', $listing?->max_guests ?? 1) }}" />
    <x-input name="bedrooms" type="number" label="Bedrooms" required value="{{ old('bedrooms', $listing?->bedrooms ?? 1) }}" />
    <x-input name="bathrooms" type="number" label="Bathrooms" required value="{{ old('bathrooms', $listing?->bathrooms ?? 1) }}" />
</div>

<div class="form-group">
    <label class="form-label">Amenities</label>
    <div class="module-grid">
        @foreach ($amenities as $amenity)
            <label class="radio-card">
                <input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" @checked(in_array($amenity->id, old('amenities', $selectedAmenityIds)))>
                {{ $amenity->name }}
            </label>
        @endforeach
    </div>
</div>

<div class="form-group">
    <label class="form-label" for="photos">Photos</label>
    <input type="file" name="photos[]" id="photos" class="form-control" multiple accept="image/*">
    <p class="form-hint">You can select multiple images. JPG/PNG, up to 5MB each.</p>
    @error('photos.*')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

@if ($listing && $listing->photoUrls()->isNotEmpty())
    <div class="form-group">
        <label class="form-label">Current photos</label>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem;">
            @foreach ($listing->getMedia('photos') as $media)
                <div>
                    <img src="{{ $media->getUrl() }}" alt="" style="width: 100%; height: 100px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 0.35rem;">
                    <form method="POST" action="{{ route('host.listings.photos.destroy', [$listing, $media]) }}" onsubmit="return confirm('Remove this photo?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline btn-block" style="color: var(--color-danger); border-color: var(--color-danger); padding: 0.3rem;">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="form-group">
    <label class="form-label" for="video_url">Video tour link</label>
    <input type="url" name="video_url" id="video_url" class="form-control" placeholder="https://youtube.com/watch?v=... or https://vimeo.com/..." value="{{ old('video_url', $listing?->video_url) }}">
    <p class="form-hint">Optional &mdash; paste a YouTube or Vimeo link, or upload a video file below.</p>
    @error('video_url')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-group">
    <label class="form-label" for="video">Upload a video</label>
    <input type="file" name="video" id="video" class="form-control" accept="video/mp4,video/quicktime,video/webm">
    <p class="form-hint">MP4, MOV, or WebM, up to 50MB.</p>
    @error('video')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

@if ($listing && $listing->videoUrls()->isNotEmpty())
    <div class="form-group">
        <label class="form-label">Current video</label>
        <video src="{{ $listing->videoUrls()->first() }}" controls style="width: 100%; max-width: 360px; border-radius: var(--radius-sm);"></video>
    </div>
@endif
