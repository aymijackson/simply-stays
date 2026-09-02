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
        <button type="button" class="gallery-grid__main" data-gallery-open data-index="0">
            @if ($items[0]['type'] === 'image')
                <img src="{{ $items[0]['src'] }}" alt="{{ $alt }}">
            @else
                <x-photo :alt="$alt" />
                <span class="gallery-grid__play"><x-icon name="play" /></span>
            @endif
            <span class="gallery-grid__expand"><x-icon name="expand" style="width:13px;height:13px;" /> View all</span>
        </button>

        @for ($i = 1; $i < min($items->count(), 5); $i++)
            <button type="button" class="gallery-grid__cell" data-gallery-open data-index="{{ $i }}">
                @if ($items[$i]['type'] === 'image')
                    <img src="{{ $items[$i]['src'] }}" alt="{{ $alt }}">
                @else
                    <x-photo :seed="$i" :alt="$alt" />
                    <span class="gallery-grid__play"><x-icon name="play" /></span>
                @endif
            </button>
        @endfor

        @for ($i = $items->count(); $i < 5; $i++)
            <div class="gallery-grid__cell"><x-photo :seed="$i + 10" :alt="$alt" /></div>
        @endfor
    </div>
@endif
