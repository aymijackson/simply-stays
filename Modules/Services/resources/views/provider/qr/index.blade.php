@extends(auth()->user()->portalLayout())
@php use Modules\Services\Support\QrSvg; @endphp

@section('title', 'QR codes — '.$service->title)

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('provider.services.edit', $service) }}">&larr; Back to {{ $service->title }}</a>

        <div style="margin: 1rem 0 1.25rem;">
            <h1 style="margin: 0;">QR codes</h1>
            <p class="text-muted" style="margin: 0.25rem 0 0;">Guests scan these to order {{ $service->title }} from their phone. No app or account needed.</p>
        </div>

        @if ($service->status !== 'published')
            <x-alert type="danger">This service is a draft. Its QR codes show "not available" until you publish it.</x-alert>
        @endif

        <form method="GET" action="{{ route('provider.services.qr.print', $service) }}" target="_blank" data-qr-form>
            <div class="qr-layout">
                <section class="card qr-generic">
                    <h2>General code</h2>
                    <p class="text-muted">For the lobby, pool, website or flyers. Guests tell you where they are when ordering.</p>
                    <div class="qr-generic__code">{!! QrSvg::make($generic->url(), 200) !!}</div>
                    <p class="qr-url"><a href="{{ $generic->url() }}" target="_blank" rel="noopener">{{ $generic->url() }}</a></p>
                    <p class="form-hint">{{ $generic->scans }} {{ \Illuminate\Support\Str::plural('scan', $generic->scans) }}@if ($generic->last_scanned_at), last {{ $generic->last_scanned_at->diffForHumans() }}@endif</p>
                    <label class="ops-check"><input type="checkbox" name="generic" value="1" checked> Include on the print sheet</label>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <a href="{{ route('provider.services.qr.download', [$service, $generic]) }}" class="btn btn-outline btn-sm">Download SVG</a>
                        <button type="submit" form="qr-toggle-{{ $generic->id }}" class="btn btn-ghost btn-sm">{{ $generic->is_active ? 'Switch off' : 'Switch on' }}</button>
                    </div>
                    @unless ($generic->is_active)<p class="form-error">Switched off. Scanning shows "not available".</p>@endunless
                </section>

                <section class="card qr-rooms">
                    <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; flex-wrap: wrap;">
                        <h2 style="margin: 0;">Room codes</h2>
                        @if ($roomOrdersAllowed && $roomsBySuite->isNotEmpty())
                            <label class="ops-check" style="margin: 0;"><input type="checkbox" data-qr-all checked> Select all rooms</label>
                        @endif
                    </div>
                    <p class="text-muted">The room is filled in for the guest, so orders arrive already addressed. Place one card in each room.</p>

                    @if (! $roomOrdersAllowed)
                        <p class="form-hint">This service is for non-guests only, so it has no room codes.</p>
                    @elseif ($roomsBySuite->isEmpty())
                        <p class="form-hint">No rooms set up yet. Rooms come from <a href="{{ route('ops.inventory.locations.index') }}">Operations &rarr; Locations</a> (apartment rooms such as "King Suite — Bedroom").</p>
                    @else
                        @foreach ($roomsBySuite as $suite => $rooms)
                            <div class="qr-suite">
                                <h3>{{ $suite }}</h3>
                                @foreach ($rooms as $room)
                                    @php $code = $codes[$room->id] ?? null; @endphp
                                    <div class="qr-room">
                                        <label class="ops-check" style="margin: 0;">
                                            <input type="checkbox" name="locations[]" value="{{ $room->getRouteKey() }}" checked data-qr-room>
                                            {{ $room->area ?? $room->name }}
                                        </label>
                                        <span class="form-hint" style="margin: 0;">
                                            @if ($code)
                                                {{ $code->scans }} {{ \Illuminate\Support\Str::plural('scan', $code->scans) }}
                                                · <a href="{{ $code->url() }}" target="_blank" rel="noopener">open</a>
                                                · <a href="{{ route('provider.services.qr.download', [$service, $code]) }}">SVG</a>
                                                · <button type="submit" form="qr-toggle-{{ $code->id }}" class="qr-link">{{ $code->is_active ? 'switch off' : 'switch on' }}</button>
                                                @unless ($code->is_active)<span class="badge badge--danger">Off</span>@endunless
                                            @else
                                                Not printed yet
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </section>
            </div>

            <div class="card qr-printbar">
                <label class="form-label" for="copies" style="margin: 0;">Copies of each</label>
                <input type="number" name="copies" id="copies" value="1" min="1" max="20" class="form-control" style="width: 90px;">
                <button type="submit" class="btn btn-primary">Open print sheet</button>
                <span class="form-hint" style="margin: 0;">Opens in a new tab, ready to print on A4.</span>
            </div>
        </form>

        {{-- Toggle forms live outside the print form so they don't nest. --}}
        @foreach (collect([$generic])->merge($codes->values()) as $code)
            <form id="qr-toggle-{{ $code->id }}" method="POST" action="{{ route('provider.services.qr.toggle', [$service, $code]) }}">@csrf @method('PATCH')</form>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('[data-qr-all]').on('change', function () { $('[data-qr-room]').prop('checked', this.checked); });
});
</script>
@endpush
