@extends(auth()->user()->portalLayout())

@section('title', 'Suggestions — ' . $menuItem->name)

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('provider.services.menu-items.options.index', [$service, $menuItem]) }}">&larr; Back to options</a>

        <h1 style="margin-top: 1rem;">Suggestions &mdash; {{ $menuItem->name }}</h1>
        <p class="form-hint">Nudge guests toward add-ons and pairings once they've added this item to their order.</p>

        @if ($errors->any())
            <x-alert type="danger">{{ $errors->first() }}</x-alert>
        @endif

        <div class="detail-layout">
            <div>
                @if ($pairings->isEmpty())
                    <div class="card text-center" style="padding: 2.5rem;">
                        <p style="margin: 0;">No suggestions set up yet.</p>
                    </div>
                @else
                    <div class="card">
                        @foreach ($pairings as $pairing)
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-top: 1px solid var(--color-border);">
                                <span>
                                    {{ $pairing->pairedMenuItem->name }}
                                    <span class="badge">{{ $pairing->type === 'upsell' ? 'Add this too' : 'Goes well with' }}</span>
                                </span>
                                <form method="POST" action="{{ route('provider.services.menu-items.pairings.destroy', [$service, $menuItem, $pairing]) }}" onsubmit="return confirm('Remove this suggestion?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger-outline btn-sm">Remove</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card">
                <h2>Add a suggestion</h2>
                @if ($otherItems->isEmpty())
                    <p class="form-hint">Add more menu items first.</p>
                @else
                    <form method="POST" action="{{ route('provider.services.menu-items.pairings.store', [$service, $menuItem]) }}">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="paired_menu_item_id">Menu item</label>
                            <select name="paired_menu_item_id" id="paired_menu_item_id" class="form-control">
                                @foreach ($otherItems as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="type">Type</label>
                            <select name="type" id="type" class="form-control">
                                <option value="upsell">Add this too (upsell)</option>
                                <option value="pairing">Goes well with (pairing)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Add suggestion</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
