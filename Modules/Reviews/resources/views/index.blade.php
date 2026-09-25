@extends(auth()->user()->portalLayout())

@section('title', 'Review moderation')

@section('content')
<div class="page page--tight">
    <div class="container">
        <h1>Review moderation</h1>

        @if ($reviews->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin: 0;">No reviews yet.</p>
            </div>
        @else
            <div class="entity-grid">
                @foreach ($reviews as $review)
                    <div class="card">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                            <div class="rating"><x-icon name="star" /> {{ $review->rating }} / 5</div>
                            <x-status-badge :status="$review->status" />
                        </div>
                        <p style="margin-bottom: 0.25rem; color: var(--color-navy-950); font-weight: 700;">
                            {{ class_basename($review->reviewable_type) }}: {{ $review->reviewable->title ?? 'Deleted' }}
                        </p>
                        <p style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                            <span class="avatar" style="width:22px;height:22px;font-size:0.7rem;">{{ strtoupper(substr($review->reviewer->name ?? '?', 0, 1)) }}</span>
                            {{ $review->reviewer->name ?? 'Unknown' }}
                        </p>
                        <p>{{ $review->comment ?: 'No comment.' }}</p>

                        <form method="POST" action="{{ route('reviews.toggle', $review) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-outline btn-sm btn-block">{{ $review->status === 'published' ? 'Hide' : 'Publish' }}</button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div style="margin-top: 2.5rem;">
                {{ $reviews->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
