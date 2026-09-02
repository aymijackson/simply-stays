@props(['label' => null, 'name', 'type' => 'text', 'required' => false, 'hint' => null, 'value' => null])

<div class="form-group">
    @if ($label)
        <label class="form-label" for="{{ $name }}">{{ $label }}</label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'form-control']) }}
    >

    @if ($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
