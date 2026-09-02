@extends('layouts.app')

@section('title', 'Leave a review')

@section('content')
<div class="page page--tight">
    <div class="container" style="max-width: 480px;">
        <div class="card">
            <h1>Leave a review</h1>
            <p><strong>{{ $title }}</strong></p>

            @if ($errors->any())
                <x-alert type="danger">{{ $errors->first() }}</x-alert>
            @endif

            <form method="POST" action="{{ $actionUrl }}">
                @csrf

                <div class="form-group">
                    <label class="form-label">Rating</label>
                    <div class="star-picker" data-star-picker data-input="rating-value">
                        @for ($i = 1; $i <= 5; $i++)
                            <button type="button" class="star-picker__star" data-value="{{ $i }}"><x-icon name="star" /></button>
                        @endfor
                    </div>
                    <input type="hidden" name="rating" id="rating-value" value="{{ old('rating', 5) }}">
                    @error('rating')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="comment">Comment</label>
                    <textarea name="comment" id="comment" class="form-control" rows="4" placeholder="How was it?">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block">Submit review</button>
            </form>
        </div>
    </div>
</div>
@endsection
