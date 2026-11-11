@props(['name', 'id' => null, 'value' => null, 'placeholder' => ''])

@php
    $id ??= $name;
    $initial = \Modules\Core\Support\RichText::toHtml($value);
@endphp

{{--
    WYSIWYG editor (Quill, loaded from CDN only on pages that use it).
    The real form field is the textarea, which is kept in sync with the editor,
    so the form still posts normally — and still works if the CDN is unreachable.
--}}
<div class="rich-editor" data-rich-editor>
    <div class="rich-editor__surface" data-rich-editor-surface data-placeholder="{{ $placeholder }}" hidden></div>
    <textarea name="{{ $name }}" id="{{ $id }}" class="form-control" rows="6" data-rich-editor-input placeholder="{{ $placeholder }}">{{ $initial }}</textarea>
</div>

@once
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
    if (typeof Quill === 'undefined') return; // CDN unavailable: the plain textarea stays usable.

    document.querySelectorAll('[data-rich-editor]').forEach(function (wrapper) {
        var surface = wrapper.querySelector('[data-rich-editor-surface]');
        var input = wrapper.querySelector('[data-rich-editor-input]');

        surface.hidden = false;
        input.hidden = true;

        var quill = new Quill(surface, {
            theme: 'snow',
            placeholder: surface.dataset.placeholder || '',
            modules: {
                toolbar: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'link'],
                    ['clean']
                ]
            },
            formats: ['header', 'bold', 'italic', 'underline', 'strike', 'list', 'blockquote', 'link']
        });

        if (input.value.trim() !== '') {
            quill.clipboard.dangerouslyPasteHTML(input.value);
        }

        function sync() {
            input.value = quill.getText().trim() === '' ? '' : quill.getSemanticHTML().replace(/&nbsp;/g, ' '); // Quill 2.0.3 emits &nbsp; for every space
        }

        quill.on('text-change', sync);
        var form = wrapper.closest('form');
        if (form) form.addEventListener('submit', sync);
    });
})();
</script>
@endpush
@endonce
