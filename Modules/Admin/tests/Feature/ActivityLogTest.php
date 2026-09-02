<?php

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_a_booking_status_change_is_recorded_in_the_activity_log(): void
    {
        $host = User::factory()->create();
        $host->assignRole('Host');

        $guest = User::factory()->create();
        $guest->assignRole('Guest');

        $property = Property::create([
            'host_id' => $host->id,
            'name' => 'Activity Log Property',
            'property_type' => 'apartment',
            'address' => '1 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $listing = Listing::create([
            'property_id' => $property->id,
            'title' => 'Activity Log Listing',
            'slug' => 'activity-log-listing',
            'base_price_amount' => 1000000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);

        $booking = Booking::create([
            'listing_id' => $listing->id,
            'guest_id' => $guest->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
            'status' => 'pending',
            'total_amount' => 2000000,
            'currency' => 'NGN',
        ]);

        $booking->update(['status' => 'confirmed']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
        ]);

        $activity = Activity::where('subject_type', Booking::class)
            ->where('subject_id', $booking->id)
            ->where('event', 'updated')
            ->latest()
            ->first();
        $this->assertSame('confirmed', $activity->properties['attributes']['status']);
        $this->assertSame('pending', $activity->properties['old']['status']);
    }

    public function test_admin_changing_a_users_role_is_recorded_with_before_and_after(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guest');

        $this->actingAs($this->admin)->patch(route('admin.users.role', $user), ['role' => 'Host']);

        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('description', 'role changed')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($this->admin->id, $activity->causer_id);
        $this->assertSame('Guest', $activity->properties['from']);
        $this->assertSame('Host', $activity->properties['to']);
    }
}
