<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR codes — {{ $service->title }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap">
    <style>
        :root { --ink: #0a2540; --muted: #5b6b7f; --line: #d7dee8; --accent: #0066cc; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink); background: #eef2f6; }
        .bar { position: sticky; top: 0; display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.75rem 1.25rem; background: #fff; border-bottom: 1px solid var(--line); }
        .bar button { font: inherit; font-weight: 700; padding: 0.55rem 1.2rem; border: 0; border-radius: 999px; background: var(--accent); color: #fff; cursor: pointer; }
        .sheet { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10mm; width: 210mm; margin: 1.5rem auto; padding: 12mm; background: #fff; }
        .card { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 8mm 6mm; border: 1.5px dashed var(--line); border-radius: 6mm; break-inside: avoid; page-break-inside: avoid; }
        .brand { font-size: 9pt; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }
        .title { margin: 2mm 0 1mm; font-size: 16pt; font-weight: 800; line-height: 1.15; }
        .room { display: inline-block; margin-bottom: 3mm; padding: 1mm 4mm; border-radius: 999px; background: #e8f1fc; color: var(--accent); font-size: 10pt; font-weight: 700; }
        .qr svg { width: 52mm; height: 52mm; display: block; }
        .cta { margin-top: 3mm; font-size: 11pt; font-weight: 700; }
        .hint { margin-top: 1mm; font-size: 8pt; color: var(--muted); }
        .empty { padding: 3rem; text-align: center; color: var(--muted); }
        @page { size: A4; margin: 10mm; }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { margin: 0; padding: 0; width: auto; gap: 8mm; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <strong>{{ $cards->count() }} {{ \Illuminate\Support\Str::plural('card', $cards->count()) }} · {{ $service->title }}</strong>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    @if ($cards->isEmpty())
        <p class="empty">Nothing selected. Go back and tick the general code or some rooms.</p>
    @else
        <div class="sheet">
            @foreach ($cards as $code)
                <div class="card">
                    <div class="brand">{{ $code->location?->property?->name ?? config('app.name') }}</div>
                    <div class="title">{{ $service->title }}</div>
                    @if ($code->location)<div class="room">{{ $code->location->label() }}</div>@endif
                    <div class="qr">{!! \Modules\Services\Support\QrSvg::make($code->url(), 300) !!}</div>
                    <div class="cta">Scan to order</div>
                    <div class="hint">{{ $code->location ? 'Delivered to this room · no app needed' : 'No app or account needed' }}</div>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
