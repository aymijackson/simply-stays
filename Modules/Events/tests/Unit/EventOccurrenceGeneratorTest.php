<?php

namespace Modules\Events\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Modules\Events\Support\EventOccurrenceGenerator;
use Tests\TestCase;

class EventOccurrenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->create();
    }

    private function makeEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'created_by' => $this->creator->id,
            'title' => 'Test Event',
            'currency' => 'NGN',
            'recurrence_type' => 'none',
            'starts_on' => now()->toDateString(),
            'start_time' => '10:00',
            'status' => 'draft',
        ], $overrides));
    }

    public function test_a_one_off_event_generates_a_single_occurrence(): void
    {
        $event = $this->makeEvent();

        EventOccurrenceGenerator::generate($event);

        $this->assertSame(1, $event->occurrences()->count());
        $this->assertSame($event->starts_on->toDateString(), $event->occurrences()->first()->date->toDateString());
    }

    public function test_weekly_recurrence_only_lands_on_the_chosen_weekday(): void
    {
        $start = now()->startOfWeek(); // Monday
        $event = $this->makeEvent([
            'recurrence_type' => 'weekly',
            'recurrence_days_of_week' => [0], // Sunday
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addWeeks(3)->toDateString(),
        ]);

        EventOccurrenceGenerator::generate($event);

        $dates = $event->occurrences()->orderBy('date')->pluck('date');

        $this->assertTrue($dates->every(fn ($date) => $date->dayOfWeek === 0));
        $this->assertGreaterThanOrEqual(3, $dates->count());
    }

    public function test_indefinite_recurrence_is_capped_at_six_months(): void
    {
        $start = now()->startOfDay();
        $event = $this->makeEvent([
            'recurrence_type' => 'daily',
            'starts_on' => $start->toDateString(),
            'ends_on' => null,
        ]);

        EventOccurrenceGenerator::generate($event);

        $lastDate = $event->occurrences()->orderByDesc('date')->first()->date;

        $this->assertTrue($lastDate->lte($start->copy()->addMonths(6)));
        $this->assertTrue($lastDate->gte($start->copy()->addMonths(5)->addWeeks(3)));
    }

    public function test_regenerating_occurrences_replaces_the_previous_set(): void
    {
        $event = $this->makeEvent();
        EventOccurrenceGenerator::generate($event);
        $this->assertSame(1, $event->occurrences()->count());

        $event->update(['recurrence_type' => 'daily', 'ends_on' => now()->addDays(4)->toDateString()]);
        EventOccurrenceGenerator::generate($event->fresh());

        $this->assertSame(5, $event->occurrences()->count());
    }
}
