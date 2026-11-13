<?php

namespace Modules\Events\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventOccurrence;

class EventsController extends Controller
{
    public function index(Request $request): View
    {
        $occurrences = EventOccurrence::query()
            ->upcoming()
            ->whereHas('event', function ($query) use ($request) {
                $query->published()
                    ->when($request->filled('city'), fn ($q) => $q->whereHas('property', fn ($pq) => $pq->where('city', 'like', '%'.$request->string('city').'%')))
                    ->when($request->filled('property'), fn ($q) => $q->whereHas('property', fn ($pq) => $pq->where('slug', $request->input('property'))));
            })
            ->with('event.property')
            ->orderBy('date')
            ->paginate(12)
            ->withQueryString();

        return view('events::index', compact('occurrences'));
    }

    public function calendar(Request $request): View
    {
        $month = $request->filled('month')
            ? Carbon::parse($request->string('month').'-01')
            : Carbon::now()->startOfMonth();

        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $occurrences = EventOccurrence::query()
            ->where('status', 'scheduled')
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->whereHas('event', fn ($query) => $query->published())
            ->with('event')
            ->get()
            ->groupBy(fn (EventOccurrence $occurrence) => $occurrence->date->toDateString());

        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $week[] = [
                    'date' => $cursor->copy(),
                    'inMonth' => $cursor->month === $month->month,
                    'occurrences' => $occurrences->get($cursor->toDateString(), collect()),
                ];
                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return view('events::calendar', [
            'month' => $month,
            'weeks' => $weeks,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function show(Event $event): View
    {
        abort_unless($event->status === 'published', 404);

        $event->load(['property', 'creator']);
        $occurrences = $event->occurrences()->upcoming()->orderBy('date')->get();

        return view('events::show', compact('event', 'occurrences'));
    }
}
