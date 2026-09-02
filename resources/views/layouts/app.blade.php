<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $siteSettings->color_theme }}" data-layout="{{ $siteSettings->layout_style }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') &mdash; @endif{{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    @include('layouts.partials.nav')

    @if (session('status'))
        <div class="container" style="padding-top: 1.5rem;">
            <x-alert type="success">{{ session('status') }}</x-alert>
        </div>
    @endif

    <main>
        @yield('content')
    </main>

    @include('layouts.partials.footer')

    <div id="lightbox-overlay" class="lightbox" style="display:none;">
        <button type="button" class="lightbox__close" data-lightbox-close aria-label="Close"><x-icon name="x" /></button>
        <button type="button" class="lightbox__prev" data-lightbox-prev aria-label="Previous"><x-icon name="chevron-left" /></button>
        <div class="lightbox__stage" data-lightbox-stage></div>
        <button type="button" class="lightbox__next" data-lightbox-next aria-label="Next"><x-icon name="chevron-right" /></button>
    </div>

    <script src="{{ asset('vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/axios.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
