@extends(auth()->user()->portalLayout())

@section('title', 'Menu — ' . $service->title)

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('provider.services.edit', $service) }}">&larr; Back to {{ $service->title }}</a>

        <h1 style="margin-top: 1rem;">Menu &mdash; {{ $service->title }}</h1>

        <div class="detail-layout">
            <div>
                @if ($menuItems->isEmpty())
                    <div class="card text-center" style="padding: 2.5rem;">
                        <p style="margin: 0;">No menu items yet. Add your first one.</p>
                    </div>
                @else
                    <div class="entity-grid">
                        @foreach ($menuItems as $item)
                            <div class="card card--flush">
                                <div class="entity-card__media" style="aspect-ratio: 4/3;">
                                    <x-photo :url="$item->coverPhotoUrl()" type="other" :seed="$item->id" :alt="$item->name" />
                                    @unless ($item->is_available)
                                        <span class="entity-card__badge"><span class="badge badge--danger">Unavailable</span></span>
                                    @endunless
                                </div>
                                <div style="padding: 1rem 1.1rem 1.2rem;">
                                    <div class="entity-card__title">{{ $item->name }}</div>
                                    <div class="entity-card__meta">{{ $item->category ?? 'Uncategorized' }} &middot; {{ $item->price()->format() }} &middot; {{ $item->optionGroups->count() }} option group(s)</div>

                                    <div style="display: flex; gap: 0.4rem; margin-top: 0.6rem; flex-wrap: wrap;">
                                        <a href="{{ route('provider.services.menu-items.options.index', [$service, $item]) }}" class="btn btn-outline btn-sm">Options</a>
                                        <a href="{{ route('provider.services.menu-items.pairings.index', [$service, $item]) }}" class="btn btn-outline btn-sm">Suggestions</a>
                                    </div>

                                    <form method="POST" action="{{ route('provider.services.menu-items.update', [$service, $item]) }}" enctype="multipart/form-data" style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $item->name }}">
                                        <input type="hidden" name="description" value="{{ $item->description }}">
                                        <input type="hidden" name="ingredients" value="{{ $item->ingredients }}">
                                        <input type="hidden" name="allergens" value="{{ implode(', ', $item->allergens ?? []) }}">
                                        <input type="hidden" name="price" value="{{ $item->price()->toDecimal() }}">
                                        <input type="hidden" name="category" value="{{ $item->category }}">
                                        <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                                            <input type="checkbox" name="is_available" value="1" @checked($item->is_available) onchange="this.form.submit()">
                                            Available on menu
                                        </label>
                                    </form>

                                    <form method="POST" action="{{ route('provider.services.menu-items.destroy', [$service, $item]) }}" onsubmit="return confirm('Remove this menu item?');" style="margin-top: 0.5rem;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger-outline btn-sm btn-block">Delete</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card">
                <h2>Add a menu item</h2>
                <form method="POST" action="{{ route('provider.services.menu-items.store', $service) }}" enctype="multipart/form-data">
                    @csrf
                    <x-input name="name" label="Name" required value="{{ old('name') }}" />
                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="ingredients">Ingredients</label>
                        <textarea name="ingredients" id="ingredients" class="form-control" rows="2">{{ old('ingredients') }}</textarea>
                    </div>
                    <x-input name="allergens" label="Allergens" hint="Comma-separated, e.g. Gluten, Dairy, Nuts" value="{{ old('allergens') }}" />
                    <x-input name="price" type="number" step="0.01" label="Price" required value="{{ old('price') }}" />
                    <x-input name="category" label="Category" hint="e.g. Starters, Mains, Drinks" value="{{ old('category') }}" />
                    <div class="form-group">
                        <label class="form-label" for="photo">Photo</label>
                        <input type="file" name="photo" id="photo" class="form-control" accept="image/*">
                    </div>
                    <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem; margin-bottom: 1rem;">
                        <input type="checkbox" name="is_available" value="1" checked>
                        Available on menu
                    </label>
                    <button type="submit" class="btn btn-primary btn-block">Add item</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
