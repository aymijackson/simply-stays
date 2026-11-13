@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\InventoryMovement; use Modules\Operations\Models\FinanceTransaction; use Modules\Operations\Support\Fmt; @endphp

@section('title', 'Record stock movement')

@section('content')
@php
    $currentType = old('type', $type);
    $oldLines = old('lines', [['item_id' => $presetItem, 'quantity' => null, 'unit_cost' => null]]);
@endphp
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Record stock movement'])

        <div class="card ops-form-card ops-form-card--wide">
            <form method="POST" action="{{ route('ops.inventory.movements.store') }}" data-movement-form>
                @csrf

                <div class="form-group">
                    <label class="form-label">What happened?</label>
                    <div class="ops-segmented">
                        @foreach (InventoryMovement::TYPES as $key => [$label, $needsFrom, $needsTo])
                            <label>
                                <input type="radio" name="type" value="{{ $key }}" data-from="{{ $needsFrom ? 1 : 0 }}" data-to="{{ $needsTo ? 1 : 0 }}" @checked($currentType === $key)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="form-hint" data-type-hint></p>
                </div>

                <div class="form-row">
                    <div class="form-group" data-from-field>
                        <label class="form-label" for="from_location_id">From</label>
                        <select name="from_location_id" id="from_location_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}" @selected((int) old('from_location_id', $presetFrom ?? ($loc->isStore() && ! $presetTo ? $loc->id : 0)) === $loc->id)>{{ $loc->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" data-to-field>
                        <label class="form-label" for="to_location_id" data-to-label>To</label>
                        <select name="to_location_id" id="to_location_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}" @selected((int) old('to_location_id', $presetTo) === $loc->id)>{{ $loc->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="form-label">Items</label>
                <div class="ops-lines" data-lines>
                    @foreach ($oldLines as $i => $line)
                        <div class="ops-line" data-line>
                            <select name="lines[{{ $i }}][item_id]" class="form-control" aria-label="Item">
                                <option value="">Choose item…</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}" data-cost="{{ Fmt::input($item->unit_cost_amount) }}" @selected((int) ($line['item_id'] ?? 0) === $item->id)>{{ $item->name }} ({{ $item->unit }})</option>
                                @endforeach
                            </select>
                            <input type="number" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? '' }}" class="form-control ops-qty" placeholder="Qty" aria-label="Quantity">
                            <input type="text" inputmode="decimal" name="lines[{{ $i }}][unit_cost]" value="{{ $line['unit_cost'] ?? '' }}" class="form-control ops-cost" placeholder="Unit cost" aria-label="Unit cost" data-cost-field>
                            <button type="button" class="btn btn-ghost btn-sm" data-remove-line aria-label="Remove line">&times;</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline btn-sm" data-add-line>+ Add another item</button>
                <p class="form-hint" data-adjust-hint hidden>For a count correction, enter the difference: a negative number removes stock, a positive number adds it. To enter a full count, use "Save count" on the location page.</p>

                <div class="form-row" style="margin-top: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label" for="staff_member_id">Handled / collected by</label>
                        <select name="staff_member_id" id="staff_member_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}" @selected((int) old('staff_member_id') === $member->id)>{{ $member->name }} ({{ $member->position }})</option>
                            @endforeach
                        </select>
                    </div>
                    <x-input name="occurred_at" label="When" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" />
                </div>

                <div class="form-group">
                    <label class="form-label" for="note">Note</label>
                    <input type="text" name="note" id="note" class="form-control" value="{{ old('note') }}" maxlength="1000" placeholder="e.g. Taken to the laundry for washing">
                </div>

                @if ($canManage)
                    <fieldset class="ops-fieldset" data-expense-fields>
                        <legend>Purchase</legend>
                        <label class="ops-check">
                            <input type="checkbox" name="post_expense" value="1" @checked(old('post_expense'))>
                            Record the cost as an expense in the books (Inventory &amp; supplies purchases)
                        </label>
                        <div class="form-row">
                            <x-input name="supplier" label="Supplier" maxlength="150" />
                            <div class="form-group">
                                <label class="form-label" for="payment_method">Paid by</label>
                                <select name="payment_method" id="payment_method" class="form-control">
                                    @foreach (FinanceTransaction::PAYMENT_METHODS as $key => $label)
                                        <option value="{{ $key }}" @selected(old('payment_method') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </fieldset>
                @endif

                <div class="ops-form-actions">
                    <a href="{{ url()->previous() }}" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary">Save movement</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    var $form = $('[data-movement-form]');
    var hints = {
        receive: 'New stock bought or donated. Usually received into the store.',
        transfer: 'Items moved from one place to another, e.g. store to kitchen or to a suite.',
        consume: 'Items used up or given out, e.g. toiletries placed for guests, detergent used.',
        write_off: 'Items broken, lost, stolen or thrown away.',
        adjust: 'Correct the record after a physical count.'
    };

    function sync() {
        var $type = $form.find('input[name=type]:checked');
        var key = $type.val();
        $form.find('[data-from-field]').toggle($type.data('from') == 1);
        $form.find('[data-to-field]').toggle($type.data('to') == 1);
        $form.find('[data-to-label]').text(key === 'adjust' ? 'Location' : (key === 'receive' ? 'Received into' : 'To'));
        $form.find('[data-type-hint]').text(hints[key] || '');
        $form.find('[data-expense-fields]').toggle(key === 'receive');
        $form.find('[data-cost-field]').toggle(key === 'receive');
        $form.find('[data-adjust-hint]').prop('hidden', key !== 'adjust');
        $form.find('.ops-qty').attr('min', key === 'adjust' ? null : 1);
    }

    $form.on('change', 'input[name=type]', sync);

    $form.on('change', '[data-line] select', function () {
        var $cost = $(this).closest('[data-line]').find('[data-cost-field]');
        if (!$cost.val()) { $cost.val($(this).find(':selected').data('cost') || ''); }
    });

    $form.on('click', '[data-add-line]', function () {
        var $lines = $form.find('[data-lines]');
        var $line = $lines.find('[data-line]').first().clone();
        var index = Date.now();
        $line.find('select, input').each(function () {
            this.name = this.name.replace(/lines\[[^\]]+\]/, 'lines[' + index + ']');
            $(this).val('');
        });
        $lines.append($line);
        $line.find('select').trigger('focus');
        sync();
    });

    $form.on('click', '[data-remove-line]', function () {
        if ($form.find('[data-line]').length > 1) { $(this).closest('[data-line]').remove(); }
    });

    sync();
});
</script>
@endpush
