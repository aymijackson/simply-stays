@if ($url)
    <img src="{{ $url }}" alt="{{ $alt }}" {{ $attributes }}>
@else
    <div {{ $attributes->merge(['class' => 'photo-placeholder '.$gradientClass()]) }}>
        <x-icon :name="$icon()" />
    </div>
@endif
