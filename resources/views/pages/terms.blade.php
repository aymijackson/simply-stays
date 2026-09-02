@extends('layouts.app')

@section('title', 'Terms & Conditions')

@section('content')
<div class="page">
    <div class="container" style="max-width: 800px;">
        <h1>Terms &amp; Conditions</h1>

        @if ($settings->terms_content)
            <div>{!! $settings->terms_content !!}</div>
        @else
            <p>Terms &amp; conditions have not been published yet. Please check back soon.</p>
        @endif
    </div>
</div>
@endsection
