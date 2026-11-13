@php
    $service = $service ?? null;
    $properties = $properties ?? collect();
    $selectedPropertyIds = $selectedPropertyIds ?? [];
    $vendors = $vendors ?? collect();
    $selectedVendorId = (int) old('provider_id', $selectedVendorId ?? auth()->id());
@endphp

@if (auth()->user()->hasRole('Admin'))
    <div class="form-group">
        <label class="form-label" for="provider_id">Vendor</label>
        <select name="provider_id" id="provider_id" class="form-control">
            <option value="{{ auth()->id() }}" @selected($selectedVendorId === auth()->id())>None — the platform runs this service ({{ auth()->user()->name }})</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected($selectedVendorId === $vendor->id)>{{ $vendor->name }} — {{ $vendor->email }}@if ($vendor->status === 'suspended') (suspended)@endif</option>
            @endforeach
            @if ($service && $service->provider_id !== auth()->id() && ! $vendors->contains('id', $service->provider_id))
                <option value="{{ $service->provider_id }}" selected>{{ $service->provider?->name ?? 'Current owner' }}</option>
            @endif
        </select>
        <p class="form-hint">The vendor sees and manages this service from their own login, and receives its earnings minus the platform commission. <a href="{{ route('admin.vendors.create') }}">Register a new vendor</a></p>
        @error('provider_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
@endif

<div class="form-group">
    <label class="form-label" for="service_category_id">Category</label>
    <select name="service_category_id" id="service_category_id" class="form-control">
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('service_category_id', $service?->service_category_id) == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    @error('service_category_id')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<x-input name="title" label="Service title" required value="{{ old('title', $service?->title) }}" />

<div class="form-group">
    <label class="form-label" for="description">Description</label>
    <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $service?->description) }}</textarea>
    @error('description')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-row">
    <x-input name="price" type="number" label="Price" required value="{{ old('price', $service?->price_amount ? $service->price()->toDecimal() : '') }}" />
    <div class="form-group">
        <label class="form-label" for="currency">Currency</label>
        <select name="currency" id="currency" class="form-control">
            @foreach (['USD', 'NGN', 'GHS', 'KES', 'ZAR', 'GBP', 'EUR'] as $code)
                <option value="{{ $code }}" @selected(old('currency', $service?->currency ?? 'USD') === $code)>{{ $code }}</option>
            @endforeach
        </select>
        @error('currency')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="form-group">
    <label class="form-label" for="audience">Who can order this</label>
    <select name="audience" id="audience" class="form-control">
        <option value="both" @selected(old('audience', $service?->audience ?? 'both') === 'both')>Anyone</option>
        <option value="guests_only" @selected(old('audience', $service?->audience) === 'guests_only')>Guests with an active booking only</option>
        <option value="non_guests_only" @selected(old('audience', $service?->audience) === 'non_guests_only')>Non-guests only</option>
    </select>
    <p class="form-hint">"Guests only" requires an active booking at this property to order, and delivers to that booking's address automatically.</p>
    @error('audience')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-group">
    <label class="form-label">Available at these properties</label>
    <p class="form-hint" style="margin-top: -0.2rem;">Leave all unchecked to make this service available everywhere.</p>
    <div class="module-grid">
        @foreach ($properties as $property)
            <label class="radio-card">
                <input type="checkbox" name="properties[]" value="{{ $property->id }}" @checked(in_array($property->id, old('properties', $selectedPropertyIds)))>
                {{ $property->name }} <span class="form-hint">({{ $property->city }})</span>
            </label>
        @endforeach
    </div>
</div>

<div class="form-row">
    <x-input name="delivery_fee" type="number" step="0.01" label="Delivery fee" hint="0 = free delivery." value="{{ old('delivery_fee', $service?->delivery_fee_amount ? $service->price()->toDecimal() : 0) }}" />
    <x-input name="free_delivery_over" type="number" step="0.01" label="Free delivery over" hint="Optional spend threshold." value="{{ old('free_delivery_over', $service?->free_delivery_over_amount) }}" />
</div>

@if (auth()->user()->hasRole('Admin'))
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="commission_type">Platform commission</label>
            <select name="commission_type" id="commission_type" class="form-control">
                @foreach (['none' => 'None', 'fixed' => 'Fixed amount', 'percentage' => 'Percentage'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('commission_type', $service?->commission_type ?? 'none') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <x-input name="commission_value" type="number" step="0.01" label="Commission value" hint="Amount or % depending on type above." value="{{ old('commission_value', $service?->commission_value) }}" />
    </div>
@endif

@if ($service && $service->category->fulfillment_type === 'food')
    <div class="form-group">
        <a href="{{ route('provider.services.menu-items.index', $service) }}" class="btn btn-outline btn-block">
            <x-icon name="utensils" style="width:16px;height:16px;" /> Manage menu
        </a>
    </div>
@endif
