@props(['type' => 'success'])

<div class="alert alert-{{ $type }}" data-alert>
    <div>{{ $slot }}</div>
    <button type="button" class="alert-dismiss" data-alert-dismiss aria-label="Dismiss">&times;</button>
</div>
