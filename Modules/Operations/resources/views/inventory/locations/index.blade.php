@extends(auth()->user()->portalLayout())

@section('title', 'Locations')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Locations'])

        <div class="ops-toolbar">
            <p class="text-muted" style="margin:0;">Stores, kitchens and every room of each apartment. Open one to count stock or restock it to par.</p>
            @if ($canManage)
                <div class="ops-toolbar__actions"><a href="{{ route('ops.inventory.locations.create') }}" class="btn btn-primary">Add location</a></div>
            @endif
        </div>

        @forelse ($groups as $type => $locations)
            <h2 class="ops-section-title">{{ $type }}</h2>
            @php $byName = $locations->groupBy('name'); @endphp
            <div class="ops-location-grid">
                @foreach ($byName as $name => $rooms)
                    <div class="card ops-location">
                        <h3>{{ $name }}</h3>
                        @if ($rooms->first()->listing)<p class="form-hint" style="margin-top:-.25rem;">Listing: {{ $rooms->first()->listing->title }}</p>@endif
                        @foreach ($rooms as $loc)
                            <a href="{{ route('ops.inventory.locations.show', $loc) }}" class="ops-row ops-row--link">
                                <span>{{ $loc->area ?? 'All stock' }}</span>
                                <span>
                                    <span class="text-muted">{{ (int) $loc->lines_count }} items · {{ (int) $loc->units_sum }} pcs</span>
                                    @if ($loc->short_count)<span class="badge badge--danger">{{ $loc->short_count }} short</span>@endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @empty
            <div class="card text-center" style="padding: 3rem;">
                <p>No locations yet. Start with your store, then add each apartment's rooms.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
