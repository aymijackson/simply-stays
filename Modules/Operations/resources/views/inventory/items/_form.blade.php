@php use Modules\Operations\Models\InventoryItem; use Modules\Operations\Support\Fmt; @endphp

<div class="form-group">
    <label class="form-label">What kind of item is this?</label>
    <div class="ops-radio-cards">
        @foreach (InventoryItem::KINDS as $key => $label)
            <label class="ops-radio-card">
                <input type="radio" name="kind" value="{{ $key }}" @checked(old('kind', $item->kind) === $key) required>
                <span><strong>{{ $label }}</strong><small>{{ InventoryItem::KIND_HINTS[$key] }}</small></span>
            </label>
        @endforeach
    </div>
</div>

<x-input name="name" label="Item name" :value="$item->name" required maxlength="150" />

<div class="form-row">
    <div class="form-group">
        <label class="form-label" for="category">Category</label>
        <input list="item-categories" name="category" id="category" class="form-control" value="{{ old('category', $item->category) }}" maxlength="80">
        <datalist id="item-categories">
            @foreach (InventoryItem::CATEGORIES as $category)<option value="{{ $category }}">@endforeach
        </datalist>
    </div>
    <div class="form-group">
        <label class="form-label" for="unit">Counted in</label>
        <input list="item-units" name="unit" id="unit" class="form-control" value="{{ old('unit', $item->unit) }}" required maxlength="20">
        <datalist id="item-units">
            @foreach (InventoryItem::UNITS as $unit)<option value="{{ $unit }}">@endforeach
        </datalist>
    </div>
</div>

<div class="form-row">
    <x-input name="reorder_level" label="Reorder level (store)" type="number" min="0" :value="$item->reorder_level" hint="Alert when the store has this many or fewer." />
    <x-input name="unit_cost" label="Unit cost" inputmode="decimal" :value="Fmt::input($item->unit_cost_amount)" hint="Latest purchase price per unit." />
    <x-input name="sku" label="Code / SKU" :value="$item->sku" maxlength="60" />
</div>

<div class="form-group">
    <label class="form-label" for="notes">Notes</label>
    <textarea name="notes" id="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes', $item->notes) }}</textarea>
</div>
