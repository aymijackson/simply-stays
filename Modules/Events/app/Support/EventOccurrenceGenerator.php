<?php

namespace Modules\Events\Support;

use Carbon\Carbon;
use Modules\Events\Models\Event;

class EventOccurrenceGenerator
{
    /**
     * Recurring events with no explicit end date are only generated up to this
     * far forward, to avoid unbounded row generation for indefinite series.
     * The host can raise `ends_on` later (while the event is still editable)
     * to push the window out further.
     */
    private const UNBOUNDED_WINDOW_MONTHS = 6;

    public static function generate(Event $event): void
    {
        $event->occurrences()->delete();

        foreach (self::datesFor($event) as $date) {
            $event->occurrences()->create([
                'date' => $date->toDateString(),
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'capacity' => $event->capacity,
                'status' => 'scheduled',
            ]);
        }
    }

    /**
     * @return array<int, Carbon>
     */
    private static function datesFor(Event $event): array
    {
        $start = Carbon::parse($event->starts_on);

        if ($event->recurrence_type === 'none') {
            return [$start];
        }

        $cap = $start->copy()->addMonths(self::UNBOUNDED_WINDOW_MONTHS);
        $end = $event->ends_on
            ? Carbon::parse($event->ends_on)->min($cap)
            : $cap;

        return match ($event->recurrence_type) {
            'daily' => self::dailyDates($start, $end),
            'weekly' => self::weeklyDates($start, $end, $event->recurrence_days_of_week ?? [$start->dayOfWeek]),
            'monthly' => self::monthlyDates($start, $end),
            default => [$start],
        };
    }

    /**
     * @return array<int, Carbon>
     */
    private static function dailyDates(Carbon $start, Carbon $end): array
    {
        $dates = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @param  array<int, int>  $daysOfWeek
     * @return array<int, Carbon>
     */
    private static function weeklyDates(Carbon $start, Carbon $end, array $daysOfWeek): array
    {
        // Normalize defensively: values may arrive as strings (e.g. straight
        // from HTML form submission) even though callers are expected to
        // store them as ints.
        $daysOfWeek = array_map('intval', $daysOfWeek);

        $dates = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if (in_array($cursor->dayOfWeek, $daysOfWeek, true)) {
                $dates[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @return array<int, Carbon>
     */
    private static function monthlyDates(Carbon $start, Carbon $end): array
    {
        $dates = [];
        $i = 0;

        while (true) {
            // Compute each occurrence from the original start date rather than
            // incrementing a running cursor, so a clamp in a short month (e.g.
            // Jan 31 -> Feb 28) doesn't permanently shift later occurrences.
            $date = $start->copy()->addMonthsNoOverflow($i);

            if ($date->gt($end)) {
                break;
            }

            $dates[] = $date;
            $i++;
        }

        return $dates;
    }
}
