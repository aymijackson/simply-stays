{{-- Food menu picker. Expects $service, $menuItems (with optionGroups.options, upsells, pairings), $orderedBefore. --}}
@if ($orderedBefore->isNotEmpty())
    <div class="form-group">
        <label class="form-label">Order again</label>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            @foreach ($orderedBefore as $item)
                <button type="button" class="btn btn-outline btn-sm" data-add-suggestion="{{ $item->id }}">+ {{ $item->name }}</button>
            @endforeach
        </div>
    </div>
@endif

<div class="form-group">
    <label class="form-label">Choose your meals</label>
    @if ($menuItems->isEmpty())
        <p class="form-hint">This menu has no available items right now.</p>
    @else
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @foreach ($menuItems as $item)
                @php $suggestions = $item->upsells->merge($item->pairings)->unique('id'); @endphp
                <div class="card" data-menu-item data-price="{{ $item->price_amount }}" style="padding: 1rem;">
                    <div style="display: flex; gap: 0.75rem;">
                        <div style="width: 64px; height: 64px; border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0;">
                            <x-photo :url="$item->coverPhotoUrl()" type="other" :seed="$item->id" :alt="$item->name" />
                        </div>
                        <div style="flex: 1;">
                            <a href="{{ route('services.menu-item', [$service, $item]) }}" style="font-weight: 700; color: var(--color-navy-950);">{{ $item->name }}</a>
                            @if ($item->category)
                                <span class="badge" style="margin-left: 0.3rem;">{{ $item->category }}</span>
                            @endif
                            @if (! empty($item->allergens))
                                <div style="margin-top: 0.25rem;">
                                    @foreach ($item->allergens as $allergen)
                                        <span class="badge badge--danger" style="font-size: 0.65rem;">{{ $allergen }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="form-hint" style="margin-top: 0.15rem;">{{ $item->price()->format() }}</div>
                        </div>
                        <input type="number" name="items[{{ $item->id }}][quantity]" min="0" value="{{ old("items.{$item->id}.quantity", 0) }}" class="form-control" data-qty-input style="width: 64px; height: 38px; align-self: flex-start;">
                    </div>

                    @if ($item->optionGroups->isNotEmpty())
                        <div style="margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px solid var(--color-border);">
                            @foreach ($item->optionGroups as $group)
                                <div style="margin-bottom: 0.6rem;">
                                    <label class="form-label" style="margin-bottom: 0.3rem;">
                                        {{ $group->name }}
                                        @if ($group->is_required)<span style="color: var(--color-danger);">*</span>@endif
                                        <span class="form-hint">({{ $group->isSingle() ? 'choose one' : 'optional add-ons' }})</span>
                                    </label>

                                    @if ($group->isSingle())
                                        @foreach ($group->options->where('is_available', true) as $option)
                                            <label class="radio-card" style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                                                <span>
                                                    <input type="radio" name="items[{{ $item->id }}][options][{{ $group->id }}]" value="{{ $option->id }}" data-option-toggle data-price-delta="{{ $option->price_delta_amount }}" @checked($option->is_default)>
                                                    {{ $option->name }}
                                                </span>
                                                @if ($option->price_delta_amount > 0)
                                                    <span class="form-hint">+{{ $option->priceDelta($item->currency)->format() }}</span>
                                                @endif
                                            </label>
                                        @endforeach
                                    @else
                                        @foreach ($group->options->where('is_available', true) as $option)
                                            <label class="radio-card" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem;">
                                                <span>
                                                    {{ $option->name }}
                                                    @if ($option->price_delta_amount > 0)
                                                        <span class="form-hint">(+{{ $option->priceDelta($item->currency)->format() }} each)</span>
                                                    @endif
                                                </span>
                                                <input type="number" name="items[{{ $item->id }}][options][{{ $group->id }}][{{ $option->id }}]" min="0" max="{{ $option->max_quantity }}" value="0" class="form-control" data-option-qty data-price-delta="{{ $option->price_delta_amount }}" style="width: 60px;">
                                            </label>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="form-hint" data-item-total-display style="margin-top: 0.4rem; text-align: right; font-weight: 700; color: var(--color-navy-950);"></div>

                    @if ($suggestions->isNotEmpty())
                        <div data-suggestions-panel style="display: none; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed var(--color-border);">
                            <p class="form-hint" style="margin-bottom: 0.4rem;">Frequently added together</p>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                @foreach ($suggestions as $suggestion)
                                    <button type="button" class="btn btn-outline btn-sm" data-add-suggestion="{{ $suggestion->id }}">+ {{ $suggestion->name }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
    @error('items')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
