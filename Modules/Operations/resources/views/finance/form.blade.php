@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\FinanceTransaction; use Modules\Operations\Support\Fmt; @endphp

@section('title', $transaction->exists ? 'Edit entry' : 'New entry')

@section('content')
@php $type = old('type', $transaction->type); @endphp
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => $transaction->exists ? 'Edit entry' : ($type === 'income' ? 'Record income' : 'Record an expense')])

        <div class="card ops-form-card">
            @if ($transaction->booking_id)
                <p class="form-hint">Posted automatically from booking {{ $transaction->booking?->referenceCode() }}.</p>
            @endif
            <form method="POST" action="{{ $transaction->exists ? route('ops.finance.update', $transaction) : route('ops.finance.store') }}" enctype="multipart/form-data" data-finance-form>
                @csrf
                @if ($transaction->exists) @method('PUT') @endif

                <div class="ops-segmented ops-segmented--2">
                    <label><input type="radio" name="type" value="income" @checked($type === 'income')><span>Income</span></label>
                    <label><input type="radio" name="type" value="expense" @checked($type === 'expense')><span>Expense</span></label>
                </div>

                <div class="form-row" style="margin-top:1rem;">
                    <x-input name="amount" label="Amount" inputmode="decimal" :value="Fmt::input($transaction->amount)" required autofocus />
                    <x-input name="occurred_on" label="Date" type="date" :value="$transaction->occurred_on?->toDateString()" required />
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="category_id">Category</label>
                        <select name="category_id" id="category_id" class="form-control" required>
                            @foreach ($categories as $catType => $list)
                                <optgroup label="{{ ucfirst($catType) }}" data-type="{{ $catType }}">
                                    @foreach ($list as $category)
                                        <option value="{{ $category->id }}" @selected((int) old('category_id', $transaction->category_id) === $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="payment_method">Paid by</label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            @foreach (FinanceTransaction::PAYMENT_METHODS as $key => $label)
                                <option value="{{ $key }}" @selected(old('payment_method', $transaction->payment_method) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <x-input name="counterparty" :label="$type === 'income' ? 'Received from' : 'Paid to'" :value="$transaction->counterparty" maxlength="150" />
                    <x-input name="reference" label="Receipt / reference no." :value="$transaction->reference" maxlength="100" />
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="2" maxlength="2000">{{ old('description', $transaction->description) }}</textarea>
                </div>

                @if ($staff->isNotEmpty())
                    <div class="form-group">
                        <label class="form-label" for="staff_member_id">Staff member (optional)</label>
                        <select name="staff_member_id" id="staff_member_id" class="form-control">
                            <option value="">—</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}" @selected((int) old('staff_member_id', $transaction->staff_member_id) === $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="form-group">
                    <label class="form-label" for="receipt">Attach receipt</label>
                    <input type="file" name="receipt" id="receipt" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <p class="form-hint">Photo or PDF, up to 10 MB. Stored privately.</p>
                    @foreach ($receipts as $media)
                        <div class="ops-row">
                            <a href="{{ route('ops.finance.receipt', [$transaction, $media]) }}" target="_blank" rel="noopener">{{ $media->file_name }}</a>
                            <button type="submit" form="receipt-delete-{{ $media->id }}" class="btn btn-ghost btn-sm">Remove</button>
                        </div>
                    @endforeach
                </div>

                <div class="ops-form-actions">
                    @if ($transaction->exists)
                        <button type="submit" form="entry-delete" class="btn btn-danger-outline" onclick="return confirm('Delete this entry?')">Delete</button>
                        <span style="flex:1"></span>
                    @else
                        <label class="ops-check" style="flex:1; margin:0;"><input type="checkbox" name="add_another" value="1"> Add another after saving</label>
                    @endif
                    <a href="{{ route('ops.finance.index') }}" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>

            @if ($transaction->exists)
                <form id="entry-delete" method="POST" action="{{ route('ops.finance.destroy', $transaction) }}">@csrf @method('DELETE')</form>
                @foreach ($receipts as $media)
                    <form id="receipt-delete-{{ $media->id }}" method="POST" action="{{ route('ops.finance.receipt.destroy', [$transaction, $media]) }}">@csrf @method('DELETE')</form>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    var $form = $('[data-finance-form]');
    function sync() {
        var type = $form.find('input[name=type]:checked').val();
        var $select = $form.find('#category_id');
        $select.find('optgroup').each(function () {
            var match = $(this).data('type') === type;
            $(this).prop('hidden', !match).prop('disabled', !match);
        });
        if ($select.find(':selected').prop('disabled') || !$select.val()) {
            $select.val($select.find('optgroup[data-type="' + type + '"] option').first().val());
        }
        $form.find('label[for=counterparty]').text(type === 'income' ? 'Received from' : 'Paid to');
    }
    $form.on('change', 'input[name=type]', sync);
    sync();
});
</script>
@endpush
