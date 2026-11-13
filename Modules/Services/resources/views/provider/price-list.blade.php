@extends(auth()->user()->portalLayout())

@section('title', 'Price list — '.$service->title)

@section('content')
@php
    $treatments = old('treatments', $list['treatments'] ?: ['Wash only', 'Wash & iron', 'Wash, iron & starch']);
    $rows = old('rows') ?? $list['garments']->map(fn ($g) => [
        'id' => $g['item']->id,
        'name' => $g['item']->name,
        'category' => $g['item']->category,
        'available' => $g['item']->is_available,
        'prices' => collect($list['treatments'])->map(fn ($t) => isset($g['prices'][$t]) && $g['prices'][$t]['available'] ? rtrim(rtrim(number_format($g['prices'][$t]['amount'] / 100, 2, '.', ''), '0'), '.') : '')->all(),
    ])->values()->all();
    $symbol = \Modules\Core\Enums\Currency::from($service->currency)->symbol();
@endphp
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('provider.services.edit', $service) }}">&larr; Back to {{ $service->title }}</a>

        <div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin: 1rem 0 1.25rem;">
            <div>
                <h1 style="margin: 0;">Price list</h1>
                <p class="text-muted" style="margin: 0.25rem 0 0;">Set a price for each item and treatment. Leave a cell blank if you don't offer that treatment for the item. Prices are per piece, in {{ $service->currency }}.</p>
            </div>
            @if (empty($rows))
                <a href="{{ route('provider.services.price-list.edit', [$service, 'template' => 1]) }}" class="btn btn-outline">Start from a typical laundry list</a>
            @endif
        </div>

        @if ($errors->any())
            <x-alert type="danger">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</x-alert>
        @endif

        <form method="POST" action="{{ route('provider.services.price-list.update', $service) }}" data-price-grid>
            @csrf
            @method('PUT')

            <div class="card card--flush ops-table-wrap">
                <table class="pl-grid">
                    <thead>
                        <tr>
                            <th class="pl-grid__item">Item</th>
                            <th class="pl-grid__cat">Group</th>
                            @foreach ($treatments as $i => $treatment)
                                <th class="pl-grid__col" data-col="{{ $i }}">
                                    <div class="pl-grid__colhead">
                                        <input type="text" name="treatments[{{ $i }}]" value="{{ $treatment }}" class="form-control" required maxlength="40" aria-label="Treatment name">
                                        <button type="button" class="pl-grid__x" data-remove-col title="Remove this column" aria-label="Remove column {{ $treatment }}">&times;</button>
                                    </div>
                                </th>
                            @endforeach
                            <th class="pl-grid__add"><button type="button" class="btn btn-ghost btn-sm" data-add-col>+ Treatment</button></th>
                            <th class="pl-grid__on" title="Shown to customers">On</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-rows>
                        @foreach ($rows as $r => $row)
                            <tr data-row>
                                <td class="pl-grid__item">
                                    <input type="hidden" name="rows[{{ $r }}][id]" value="{{ $row['id'] ?? '' }}">
                                    <input type="text" name="rows[{{ $r }}][name]" value="{{ $row['name'] }}" class="form-control" required maxlength="100" placeholder="e.g. Shirt" aria-label="Item">
                                </td>
                                <td class="pl-grid__cat"><input type="text" name="rows[{{ $r }}][category]" value="{{ $row['category'] ?? '' }}" class="form-control" list="pl-groups" maxlength="60" placeholder="Clothing" aria-label="Group"></td>
                                @foreach ($treatments as $i => $treatment)
                                    <td class="pl-grid__col" data-col="{{ $i }}">
                                        <span class="pl-grid__money"><span>{{ $symbol }}</span><input type="text" inputmode="decimal" name="rows[{{ $r }}][prices][{{ $i }}]" value="{{ $row['prices'][$i] ?? '' }}" class="form-control" placeholder="—" aria-label="{{ $row['name'] }} — {{ $treatment }}"></span>
                                    </td>
                                @endforeach
                                <td class="pl-grid__add"></td>
                                <td class="pl-grid__on">
                                    <input type="hidden" name="rows[{{ $r }}][available]" value="0">
                                    <input type="checkbox" name="rows[{{ $r }}][available]" value="1" @checked($row['available'] ?? true) aria-label="Show {{ $row['name'] }} to customers">
                                </td>
                                <td><button type="button" class="pl-grid__x" data-remove-row title="Remove this item" aria-label="Remove {{ $row['name'] }}">&times;</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <datalist id="pl-groups"><option value="Clothing"><option value="Household"><option value="Special care"></datalist>

            <div class="ops-form-actions">
                <button type="button" class="btn btn-outline" data-add-row style="margin-right:auto;">+ Add item</button>
                <a href="{{ route('provider.services.edit', $service) }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Save price list</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    var $form = $('[data-price-grid]');
    var symbol = @json($symbol);

    function nextIndex(selector, attr) {
        var max = -1;
        $form.find(selector).each(function () { max = Math.max(max, parseInt($(this).attr(attr), 10) || 0); });
        return max + 1;
    }

    $form.on('click', '[data-add-col]', function () {
        if ($form.find('thead [data-col]').length >= 8) { return alert('Up to 8 treatments.'); }
        var i = nextIndex('thead [data-col]', 'data-col');
        $('<th class="pl-grid__col" data-col="' + i + '"><div class="pl-grid__colhead"><input type="text" name="treatments[' + i + ']" class="form-control" required maxlength="40" placeholder="e.g. Dry clean" aria-label="Treatment name"><button type="button" class="pl-grid__x" data-remove-col aria-label="Remove column">&times;</button></div></th>')
            .insertBefore($form.find('thead .pl-grid__add'))
            .find('input').trigger('focus');
        $form.find('[data-row]').each(function () {
            var r = $(this).data('row-index');
            var name = $(this).find('input[name$="[name]"]').attr('name').replace('[name]', '[prices][' + i + ']');
            $('<td class="pl-grid__col" data-col="' + i + '"><span class="pl-grid__money"><span>' + symbol + '</span><input type="text" inputmode="decimal" name="' + name + '" class="form-control" placeholder="—"></span></td>')
                .insertBefore($(this).find('.pl-grid__add'));
        });
    });

    $form.on('click', '[data-remove-col]', function () {
        if ($form.find('thead [data-col]').length <= 1) { return alert('Keep at least one treatment.'); }
        var i = $(this).closest('[data-col]').attr('data-col');
        $form.find('[data-col="' + i + '"]').remove();
    });

    $form.on('click', '[data-add-row]', function () {
        var r = 'n' + Date.now();
        var $row = $('<tr data-row></tr>');
        $row.append('<td class="pl-grid__item"><input type="hidden" name="rows[' + r + '][id]" value=""><input type="text" name="rows[' + r + '][name]" class="form-control" required maxlength="100" placeholder="e.g. Shirt"></td>');
        $row.append('<td class="pl-grid__cat"><input type="text" name="rows[' + r + '][category]" class="form-control" list="pl-groups" maxlength="60" placeholder="Clothing"></td>');
        $form.find('thead [data-col]').each(function () {
            var i = $(this).attr('data-col');
            $row.append('<td class="pl-grid__col" data-col="' + i + '"><span class="pl-grid__money"><span>' + symbol + '</span><input type="text" inputmode="decimal" name="rows[' + r + '][prices][' + i + ']" class="form-control" placeholder="—"></span></td>');
        });
        $row.append('<td class="pl-grid__add"></td><td class="pl-grid__on"><input type="hidden" name="rows[' + r + '][available]" value="0"><input type="checkbox" name="rows[' + r + '][available]" value="1" checked></td><td><button type="button" class="pl-grid__x" data-remove-row aria-label="Remove item">&times;</button></td>');
        $form.find('[data-rows]').append($row);
        $row.find('input[type=text]').first().trigger('focus');
    });

    $form.on('click', '[data-remove-row]', function () {
        $(this).closest('[data-row]').remove();
    });
});
</script>
@endpush
