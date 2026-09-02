@extends('layouts.app')

@section('title', 'Privacy Policy')

@section('content')
<div class="page">
    <div class="container" style="max-width: 800px;">
        <h1>Privacy Policy</h1>

        @if ($settings->privacy_content)
            <div>{!! $settings->privacy_content !!}</div>
        @else
            <p>Our privacy policy has not been published yet. Please check back soon.</p>
        @endif
    </div>
</div>
@endsection
