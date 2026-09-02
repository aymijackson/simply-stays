<?php

namespace Modules\Reviews\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Reviews\Models\Review;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ServiceOrder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private User $host;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->guest = User::factory()->create();
        $this->guest->assignRole('Guest');

        $this->host = User::factory()->create();
        $this->host->assignRole('Host');

        $property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'Review Test Property',
            'property_type' => 'apartment',
            'address' => '1 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $this->listing = Listing::create([
            'property_id' => $property->id,
            'title' => 'Review Test Listing',
            'slug' => 'review-test-listing',
            'base_price_amount' => 1000000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);
    }

    private function pastBooking(string $status = 'confirmed'): Booking
    {
        return Booking::create([
            'listing_id' => $this->listing->id,
            'guest_id' => $this->guest->id,
            'check_in' => now()->subDays(10)->toDateString(),
            'check_out' => now()->subDays(7)->toDateString(),
            'guests_count' => 1,
            'status' => $status,
            'total_amount' => 3000000,
            'currency' => 'NGN',
        ]);
    }

    public function test_guest_cannot_review_before_the_stay_has_ended(): void
    {
        $futureBooking = Booking::create([
            'listing_id' => $this->listing->id,
            'guest_id' => $this->guest->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
            'status' => 'confirmed',
            'total_amount' => 2000000,
            'currency' => 'NGN',
        ]);

        $this->actingAs($this->guest)
            ->get(route('bookings.review.create', $futureBooking))
            ->assertStatus(422);
    }

    public function test_guest_can_review_a_completed_past_stay(): void
    {
        $booking = $this->pastBooking();

        $response = $this->actingAs($this->guest)->post(route('bookings.review.store', $booking), [
            'rating' => 5,
            'comment' => 'Wonderful stay!',
        ]);

        $response->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('reviews', [
            'reviewable_type' => Listing::class,
            'reviewable_id' => $this->listing->id,
            'reviewer_id' => $this->guest->id,
            'rating' => 5,
            'status' => 'published',
        ]);

        $this->assertSame(5.0, $this->listing->fresh()->averageRating());
        $this->assertSame(1, $this->listing->fresh()->reviewsCount());
    }

    public function test_guest_cannot_review_the_same_listing_twice(): void
    {
        $booking = $this->pastBooking();

        Review::create([
            'reviewable_type' => Listing::class,
            'reviewable_id' => $this->listing->id,
            'reviewer_id' => $this->guest->id,
            'rating' => 4,
            'status' => 'published',
        ]);

        $this->actingAs($this->guest)
            ->post(route('bookings.review.store', $booking), ['rating' => 5])
            ->assertStatus(422);

        $this->assertSame(1, Review::count());
    }

    public function test_a_different_user_cannot_review_someone_elses_booking(): void
    {
        $booking = $this->pastBooking();
        $stranger = User::factory()->create();
        $stranger->assignRole('Guest');

        $this->actingAs($stranger)
            ->get(route('bookings.review.create', $booking))
            ->assertStatus(403);
    }

    public function test_hidden_reviews_do_not_count_toward_the_average_rating(): void
    {
        Review::create([
            'reviewable_type' => Listing::class,
            'reviewable_id' => $this->listing->id,
            'reviewer_id' => $this->guest->id,
            'rating' => 1,
            'status' => 'hidden',
        ]);

        $this->assertNull($this->listing->fresh()->averageRating());
        $this->assertSame(0, $this->listing->fresh()->reviewsCount());
    }

    public function test_admin_can_toggle_a_review_between_published_and_hidden(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $review = Review::create([
            'reviewable_type' => Listing::class,
            'reviewable_id' => $this->listing->id,
            'reviewer_id' => $this->guest->id,
            'rating' => 5,
            'status' => 'published',
        ]);

        $this->actingAs($admin)->patch(route('reviews.toggle', $review))->assertRedirect();
        $this->assertSame('hidden', $review->fresh()->status);

        $this->actingAs($admin)->patch(route('reviews.toggle', $review))->assertRedirect();
        $this->assertSame('published', $review->fresh()->status);
    }

    public function test_non_admin_cannot_access_the_moderation_queue(): void
    {
        $this->actingAs($this->guest)->get(route('reviews.index'))->assertStatus(403);
    }

    public function test_customer_can_review_a_completed_service_order(): void
    {
        $provider = User::factory()->create();
        $category = ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);
        $service = Service::create([
            'provider_id' => $provider->id,
            'service_category_id' => $category->id,
            'title' => 'Laundry',
            'price_amount' => 200000,
            'currency' => 'NGN',
            'status' => 'published',
        ]);

        $order = ServiceOrder::create([
            'service_id' => $service->id,
            'customer_id' => $this->guest->id,
            'status' => 'completed',
            'fulfillment_details' => [],
            'total_amount' => 200000,
            'currency' => 'NGN',
        ]);

        $this->actingAs($this->guest)
            ->post(route('service-orders.review.store', $order), ['rating' => 4, 'comment' => 'Quick and easy.'])
            ->assertRedirect(route('service-orders.show', $order));

        $this->assertDatabaseHas('reviews', [
            'reviewable_type' => Service::class,
            'reviewable_id' => $service->id,
            'reviewer_id' => $this->guest->id,
            'rating' => 4,
        ]);
    }

    public function test_customer_cannot_review_a_service_order_that_is_not_yet_completed(): void
    {
        $provider = User::factory()->create();
        $category = ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);
        $service = Service::create([
            'provider_id' => $provider->id,
            'service_category_id' => $category->id,
            'title' => 'Laundry',
            'price_amount' => 200000,
            'currency' => 'NGN',
            'status' => 'published',
        ]);

        $order = ServiceOrder::create([
            'service_id' => $service->id,
            'customer_id' => $this->guest->id,
            'status' => 'confirmed',
            'fulfillment_details' => [],
            'total_amount' => 200000,
            'currency' => 'NGN',
        ]);

        $this->actingAs($this->guest)
            ->get(route('service-orders.review.create', $order))
            ->assertStatus(422);
    }
}
