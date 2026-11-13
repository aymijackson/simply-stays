@extends(auth()->user()->portalLayout())

@section('title', 'Categories')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Accounting'])
        @include('operations::finance.partials.nav')

        <div class="ops-columns">
            @foreach (['income' => 'Income categories', 'expense' => 'Expense categories'] as $type => $heading)
                <div class="card ops-card">
                    <h3 class="ops-card__title">{{ $heading }}</h3>
                    @foreach ($categories[$type] ?? [] as $category)
                        <div class="ops-row">
                            <span>{{ $category->name }} @if ($category->isShared())<span class="badge">Default</span>@endif</span>
                            <span>
                                <span class="text-muted">{{ $category->transactions_count }} entries</span>
                                @unless ($category->isShared())
                                    <form method="POST" action="{{ route('ops.finance.categories.destroy', $category) }}" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm" onclick="return confirm('Delete this category?')">Delete</button>
                                    </form>
                                @endunless
                            </span>
                        </div>
                    @endforeach
                    <form method="POST" action="{{ route('ops.finance.categories.store') }}" class="ops-inline-form" style="margin-top:1rem;">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">
                        <input type="text" name="name" class="form-control" placeholder="New {{ $type }} category" required maxlength="80">
                        <button class="btn btn-outline">Add</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
