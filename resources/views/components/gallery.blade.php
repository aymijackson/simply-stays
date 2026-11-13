@props(['images' => [], 'video' => null, 'alt' => ''])

@php
    $images = collect($images)->values();

    $videoItem = null;
    if ($video) {
        $embedUrl = null;
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/watch\?v=)([\w-]+)/', $video, $m)) {
            $embedUrl = 'https://www.youtube.com/embed/'.$m[1];
        } elseif (preg_match('/vimeo\.com\/(\d+)/', $video, $m)) {
            $embedUrl = 'https://player.vimeo.com/video/'.$m[1];
        }
        $videoItem = $embedUrl
            ? ['type' => 'embed', 'src' => $embedUrl]
            : ['type' => 'video', 'src' => $video];
    }

    $items = $images->map(fn ($url) => ['type' => 'image', 'src' => $url, 'alt' => $alt])->values();
    if ($videoItem) {
        $items->push($videoItem);
    }

    $photoCount = $images->count();
    $hidden = max(0, $items->count() - 5);
@endphp

@if ($items->isEmpty())
    <div class="gallery-grid">
        <div class="gallery-grid__main"><x-photo :alt="$alt" /></div>
        @for ($i = 0; $i < 4; $i++)
            <div class="gallery-grid__cell"><x-photo :seed="$i + 1" :alt="$alt" /></div>
        @endfor
    </div>
@else
    <div class="gallery-grid" data-gallery data-items='@json($items)'>
        {{-- Main tile: a slideshow of every photo. Click opens the full-screen viewer at that photo. --}}
        <div class="gallery-grid__main gallery-show" data-gallery-show>
            @if ($photoCount)
                @foreach ($images as $i => $src)
                    <img src="{{ $src }}" alt="{{ $alt }} — photo {{ $i + 1 }} of {{ $photoCount }}" class="gallery-show__slide @if ($i === 0) is-active @endif" data-slide="{{ $i }}" @if ($i > 0) loading="lazy" @endif>
                @endforeach
            @else
                <x-photo :alt="$alt" />
                <span class="gallery-grid__play"><x-icon name="play" /></span>
            @endif

            <button type="button" class="gallery-show__open" data-gallery-open data-index="0" aria-label="Open photos full screen"></button>

            @if ($photoCount > 1)
                <button type="button" class="gallery-show__arrow gallery-show__arrow--prev" data-show-prev aria-label="Previous photo"><x-icon name="chevron-left" /></button>
                <button type="button" class="gallery-show__arrow gallery-show__arrow--next" data-show-next aria-label="Next photo"><x-icon name="chevron-right" /></button>
                <div class="gallery-show__dots" role="tablist" aria-label="Choose photo">
                    @foreach ($images as $i => $src)
                        <button type="button" class="@if ($i === 0) is-active @endif" data-show-dot="{{ $i }}" aria-label="Photo {{ $i + 1 }}"></button>
                    @endforeach
                </div>
            @endif

            <span class="gallery-grid__expand"><x-icon name="expand" style="width:13px;height:13px;" />
                {{ $photoCount > 1 ? "View all {$photoCount} photos" : 'View photo' }}{{ $videoItem ? ' & video' : '' }}
            </span>
        </div>

        @for ($i = 1; $i < min($items->count(), 5); $i++)
            <button type="button" class="gallery-grid__cell" data-gallery-open data-index="{{ $i }}" aria-label="Open photo {{ $i + 1 }}">
                @if ($items[$i]['type'] === 'image')
                    <img src="{{ $items[$i]['src'] }}" alt="{{ $alt }}" loading="lazy">
                @else
                    <x-photo :seed="$i" :alt="$alt" />
                    <span class="gallery-grid__play"><x-icon name="play" /></span>
                @endif
                @if ($i === 4 && $hidden > 0)
                    <span class="gallery-grid__more">+{{ $hidden }} more</span>
                @endif
            </button>
        @endfor

        @for ($i = $items->count(); $i < 5; $i++)
            <div class="gallery-grid__cell"><x-photo :seed="$i + 10" :alt="$alt" /></div>
        @endfor
    </div>
@endif
