@extends(auth()->user()->portalLayout())

@section('title', 'Edit '.$item->name)

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Edit item'])

        <div class="card ops-form-card">
            <form method="POST" action="{{ route('ops.inventory.items.update', $item) }}">
                @csrf
                @method('PUT')
                @include('operations::inventory.items._form')

                <label class="ops-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))>
                    Active (untick to archive — archived items are hidden from stock lists and forms)
                </label>

                <div class="ops-form-actions">
                    <button type="submit" form="item-delete" class="btn btn-danger-outline" onclick="return confirm('Delete this item? Items with stock history are archived instead.')">Delete</button>
                    <span style="flex:1"></span>
                    <a href="{{ route('ops.inventory.items.show', $item) }}" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary">Save changes</button>
                </div>
            </form>
            {{-- Kept outside the edit form: a nested form would submit DELETE to the wrong place. --}}
            <form id="item-delete" method="POST" action="{{ route('ops.inventory.items.destroy', $item) }}">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>
</div>
@endsection
