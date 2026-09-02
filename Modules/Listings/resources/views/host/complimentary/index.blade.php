@extends('layouts.app')

@section('title', 'Complimentary offers — ' . $listing->title)

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('host.listings.edit', $listing) }}">&larr; Back to {{ $listing->title }}</a>

        <h1 style="margin-top: 1rem;">Complimentary offers &mdash; {{ $listing->title }}</h1>
        <p class="form-hint">Give guests free items from a menu when their booking meets a guest-count threshold &mdash; e.g. complimentary breakfast for 3+ guests.</p>

        <div class="detail-layout">
            <div>
                @if ($rules->isEmpty())
                    <div class="card text-center" style="padding: 2.5rem;">
                        <p style="margin: 0;">No complimentary offers set up yet.</p>
                    </div>
                @else
                    @foreach ($rules as $rule)
                        <div class="card" style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <h3 style="margin: 0 0 0.25rem;">{{ $rule->label }}</h3>
                                    <p class="form-hint" style="margin: 0;">{{ $rule->service->title }} &middot; {{ $rule->min_guests }}+ guests &middot; up to {{ $rule->max_quantity_per_guest }} item(s)/guest</p>
                                </div>
                                <form method="POST" action="{{ route('host.listings.complimentary.destroy', [$listing, $rule]) }}" onsubmit="return confirm('Remove this offer?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger-outline btn-sm">Remove</button>
                                </form>
                            </div>
                            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.6rem;">
                                @foreach ($rule->menuItems as $item)
                                    <span class="badge">{{ $item->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="card">
                <h2>Add an offer</h2>
                @if ($foodServices->isEmpty())
                    <p class="form-hint">You need a food/menu service available at this property first.</p>
                @else
                    <form method="POST" action="{{ route('host.listings.complimentary.store', $listing) }}" id="complimentary-form">
                        @csrf
                        <x-input name="label" label="Label" required placeholder="e.g. Complimentary breakfast" value="{{ old('label') }}" />

                        <div class="form-group">
                            <label class="form-label" for="service_id">Menu service</label>
                            <select name="service_id" id="service_id" class="form-control" data-services-menu>
                                @foreach ($foodServices as $service)
                                    <option value="{{ $service->id }}" data-menu-items='@json($service->menuItems->pluck("name", "id"))' @selected(old('service_id') == $service->id)>{{ $service->title }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-row">
                            <x-input name="min_guests" type="number" label="Minimum guests" required value="{{ old('min_guests', 1) }}" />
                            <x-input name="max_quantity_per_guest" type="number" label="Items per guest" required value="{{ old('max_quantity_per_guest', 1) }}" />
                        </div>

                        <div class="form-group" id="menu-items-picker">
                            <label class="form-label">Eligible menu items</label>
                            <div class="module-grid" id="menu-items-options"></div>
                            @error('menu_items')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Create offer</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    var $select = $('[data-services-menu]');
    var $options = $('#menu-items-options');

    function renderOptions() {
        var items = $select.find(':selected').data('menu-items') || {};
        $options.empty();
        $.each(items, function (id, name) {
            $options.append(
                '<label class="radio-card"><input type="checkbox" name="menu_items[]" value="' + id + '"> ' + name + '</label>'
            );
        });
    }

    $select.on('change', renderOptions);
    renderOptions();
});
</script>
@endsection
