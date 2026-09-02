@extends('layouts.app')

@section('title', 'Options — ' . $menuItem->name)

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('provider.services.menu-items.index', $service) }}">&larr; Back to menu</a>

        <h1 style="margin-top: 1rem;">Options &amp; add-ons &mdash; {{ $menuItem->name }}</h1>
        <p class="form-hint">
            <a href="{{ route('provider.services.menu-items.pairings.index', [$service, $menuItem]) }}">Manage suggestions for this item &rarr;</a>
        </p>

        @if ($errors->any())
            <x-alert type="danger">{{ $errors->first() }}</x-alert>
        @endif

        <div class="detail-layout">
            <div>
                @if ($groups->isEmpty())
                    <div class="card text-center" style="padding: 2.5rem;">
                        <p style="margin: 0;">No option groups yet &mdash; e.g. "Choose your protein" or "Add extras".</p>
                    </div>
                @else
                    @foreach ($groups as $group)
                        <div class="card" style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <h3 style="margin: 0 0 0.15rem;">{{ $group->name }}</h3>
                                    <p class="form-hint" style="margin: 0;">
                                        {{ $group->isSingle() ? 'Choose one' : 'Add extras' }}
                                        @if ($group->is_required) &middot; required @endif
                                        @if ($group->max_select) &middot; max {{ $group->max_select }} @endif
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('provider.services.menu-items.option-groups.destroy', [$service, $menuItem, $group]) }}" onsubmit="return confirm('Remove this group and its options?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger-outline btn-sm">Remove group</button>
                                </form>
                            </div>

                            <div style="margin-top: 0.75rem;">
                                @foreach ($group->options as $option)
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; border-top: 1px solid var(--color-border);">
                                        <span>
                                            {{ $option->name }}
                                            @if ($option->price_delta_amount > 0)
                                                <span class="form-hint">+{{ $option->priceDelta($menuItem->currency)->format() }}</span>
                                            @endif
                                            @if (! $group->isSingle() && $option->max_quantity > 1)
                                                <span class="form-hint">(max {{ $option->max_quantity }})</span>
                                            @endif
                                            @unless ($option->is_available)
                                                <span class="badge badge--danger">Unavailable</span>
                                            @endunless
                                        </span>
                                        <form method="POST" action="{{ route('provider.services.menu-items.options.destroy', [$service, $menuItem, $group, $option]) }}" onsubmit="return confirm('Remove this option?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger-outline btn-sm">Remove</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>

                            <form method="POST" action="{{ route('provider.services.menu-items.options.store', [$service, $menuItem, $group]) }}" style="margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px dashed var(--color-border);">
                                @csrf
                                <div class="form-row">
                                    <x-input name="name" label="Option name" required placeholder="e.g. Chicken" />
                                    <x-input name="price_delta" type="number" step="0.01" label="Extra cost" value="0" />
                                    @unless ($group->isSingle())
                                        <x-input name="max_quantity" type="number" label="Max quantity" value="1" />
                                    @endunless
                                </div>
                                <button type="submit" class="btn btn-outline btn-sm">Add option</button>
                            </form>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="card">
                <h2>Add an option group</h2>
                <form method="POST" action="{{ route('provider.services.menu-items.option-groups.store', [$service, $menuItem]) }}">
                    @csrf
                    <x-input name="name" label="Group name" required placeholder="e.g. Choose your protein" value="{{ old('name') }}" />

                    <div class="form-group">
                        <label class="form-label" for="selection_type">Selection type</label>
                        <select name="selection_type" id="selection_type" class="form-control">
                            <option value="single">Single choice (mutually exclusive)</option>
                            <option value="multiple">Multiple / quantity-based add-ons</option>
                        </select>
                    </div>

                    <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem; margin-bottom: 1rem;">
                        <input type="checkbox" name="is_required" value="1">
                        Required
                    </label>

                    <div class="form-row">
                        <x-input name="min_select" type="number" label="Min select" value="0" />
                        <x-input name="max_select" type="number" label="Max select" hint="Leave blank for unlimited." />
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Add group</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
