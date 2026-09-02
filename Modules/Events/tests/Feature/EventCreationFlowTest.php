<?php

namespace Modules\Events\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventSetting;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventCreationFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $provider;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'ServiceProvider', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->host = User::factory()->create();
        $this->host->assignRole('Host');

        $this->provider = User::factory()->create();
        $this->provider->assignRole('ServiceProvider');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'My Event',
            'currency' => 'NGN',
            'recurrence_type' => 'none',
            'starts_on' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
        ], $overrides);
    }

    public function test_the_manage_index_page_is_not_shadowed_by_the_guest_slug_route(): void
    {
        // Regression guard: events/manage must not be swallowed by the
        // events/{event:slug} catch-all registered in the same route file.
        $this->actingAs($this->host)->get(route('events.manage.index'))->assertOk();
    }

    public function test_a_host_created_event_starts_pending_approval(): void
    {
        $this->actingAs($this->host)->post(route('events.manage.store'), $this->payload())->assertRedirect();

        $event = Event::first();
        $this->assertSame('pending_approval', $event->status);
        $this->assertSame(1, $event->occurrences()->count());
    }

    public function test_an_admin_created_event_is_published_immediately(): void
    {
        $this->actingAs($this->admin)->post(route('events.manage.store'), $this->payload())->assertRedirect();

        $event = Event::first();
        $this->assertSame('published', $event->status);
        $this->assertNotNull($event->approved_at);
    }

    public function test_provider_creation_is_blocked_when_the_setting_is_disabled(): void
    {
        EventSetting::current()->update(['providers_can_create' => false]);

        $this->actingAs($this->provider)
            ->post(route('events.manage.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Event::count());
    }

    public function test_provider_creation_is_allowed_when_the_setting_is_enabled(): void
    {
        EventSetting::current()->update(['providers_can_create' => true]);

        $this->actingAs($this->provider)
            ->post(route('events.manage.store'), $this->payload())
            ->assertRedirect();

        $this->assertSame(1, Event::count());
    }

    public function test_host_and_provider_flags_are_independent(): void
    {
        EventSetting::current()->update(['hosts_can_create' => false, 'providers_can_create' => true]);

        $this->actingAs($this->host)->post(route('events.manage.store'), $this->payload())->assertForbidden();
        $this->actingAs($this->provider)->post(route('events.manage.store'), $this->payload())->assertRedirect();
    }

    public function test_weekly_recurrence_days_submitted_as_form_strings_still_generate_occurrences(): void
    {
        // Regression guard: HTML form submission sends recurrence_days_of_week
        // as an array of numeric strings, not ints — the generator must not
        // silently produce zero occurrences because of a strict type mismatch.
        $this->actingAs($this->host)->post(route('events.manage.store'), $this->payload([
            'recurrence_type' => 'weekly',
            'recurrence_days_of_week' => ['3'],
            'starts_on' => now()->next(3)->toDateString(),
        ]))->assertRedirect();

        $event = Event::first();
        $this->assertGreaterThan(0, $event->occurrences()->count());
    }

    public function test_admin_can_approve_a_pending_event(): void
    {
        $this->actingAs($this->host)->post(route('events.manage.store'), $this->payload());
        $event = Event::first();

        $this->actingAs($this->admin)
            ->patch(route('admin.events.approve', $event))
            ->assertRedirect();

        $this->assertSame('published', $event->fresh()->status);
    }

    public function test_admin_can_reject_a_pending_event_with_a_reason(): void
    {
        $this->actingAs($this->host)->post(route('events.manage.store'), $this->payload());
        $event = Event::first();

        $this->actingAs($this->admin)
            ->patch(route('admin.events.reject', $event), ['rejection_reason' => 'Not appropriate for this venue.'])
            ->assertRedirect();

        $event->refresh();
        $this->assertSame('rejected', $event->status);
        $this->assertSame('Not appropriate for this venue.', $event->rejection_reason);
    }

    public function test_guests_only_see_published_events_in_the_list(): void
    {
        $this->actingAs($this->admin)->post(route('events.manage.store'), $this->payload(['title' => 'Published Event']));
        $this->actingAs($this->host)->post(route('events.manage.store'), $this->payload(['title' => 'Pending Event']));

        $response = $this->get(route('events.index'));

        $response->assertOk();
        $response->assertSee('Published Event');
        $response->assertDontSee('Pending Event');
    }
}
