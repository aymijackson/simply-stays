<?php

namespace Modules\Bookings\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Models\GuestRegistration;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private User $host;

    private User $guest;

    private User $admin;

    private Property $property;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->host = User::factory()->create();
        $this->host->assignRole('Host');
        $this->guest = User::factory()->create(['name' => 'Ada Guest']);
        $this->guest->assignRole('Guest');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'Premium Stay',
            'property_type' => 'apartment',
            'address' => 'No 6 Etim Expo Crescent',
            'city' => 'Calabar',
            'country' => 'Nigeria',
        ]);

        $listing = Listing::create([
            'property_id' => $this->property->id,
            'title' => 'Executive Suite',
            'slug' => 'executive-suite',
            'base_price_amount' => 5000000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);

        $this->booking = Booking::create([
            'listing_id' => $listing->id,
            'guest_id' => $this->guest->id,
            'check_in' => '2026-12-01',
            'check_out' => '2026-12-04',
            'guests_count' => 2,
            'status' => 'confirmed',
            'total_amount' => 15000000,
            'currency' => 'NGN',
        ]);
    }

    public function test_create_form_prefills_from_booking(): void
    {
        $this->actingAs($this->guest)
            ->get(route('guest-forms.create', ['booking' => $this->booking->id]))
            ->assertOk()
            ->assertSee('Premium Stay')
            ->assertSee('value="Ada Guest"', false)
            ->assertSee('value="Executive Suite"', false)
            ->assertSee('value="2026-12-01"', false)
            ->assertSee('value="3"', false);
    }

    public function test_property_only_gives_blank_default_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('guest-forms.create', ['property' => $this->property->id]))
            ->assertOk()
            ->assertSee('1. Guest information')
            ->assertSee('8. For office use only');
    }

    public function test_guest_must_sign_and_cannot_set_staff_fields(): void
    {
        $payload = [
            'property_id' => $this->property->id,
            'booking_id' => $this->booking->id,
            'data' => ['full_name' => 'Ada Guest', 'room_assigned' => 'Penthouse', 'amount_paid' => 'all of it'],
        ];

        $this->actingAs($this->guest)->post(route('guest-forms.store'), $payload)
            ->assertSessionHasErrors(['signature', 'acknowledged']);

        $this->actingAs($this->guest)->post(route('guest-forms.store'), $payload + ['signature' => self::SIGNATURE, 'acknowledged' => '1'])
            ->assertRedirect();

        $registration = GuestRegistration::sole();
        $this->assertSame($this->guest->id, $registration->guest_id);
        $this->assertNotNull($registration->signed_at);
        $this->assertNull($registration->value('room_assigned'));
        $this->assertNull($registration->value('amount_paid'));
    }

    public function test_staff_can_fill_office_fields_without_signature_and_guest_edit_keeps_them(): void
    {
        $this->actingAs($this->host)->post(route('guest-forms.store'), [
            'property_id' => $this->property->id,
            'booking_id' => $this->booking->id,
            'data' => ['full_name' => 'Ada Guest', 'room_assigned' => 'Suite 4'],
        ])->assertRedirect();

        $registration = GuestRegistration::sole();
        $this->assertSame($this->guest->id, $registration->guest_id);

        $this->actingAs($this->guest)->put(route('guest-forms.update', $registration), [
            'data' => ['full_name' => 'Ada Guest', 'vehicle_type' => 'Sedan', 'room_assigned' => 'Hacked'],
            'signature' => self::SIGNATURE,
        ])->assertRedirect(route('guest-forms.show', $registration));

        $registration->refresh();
        $this->assertSame('Suite 4', $registration->value('room_assigned'));
        $this->assertSame('Sedan', $registration->value('vehicle_type'));
        $this->assertNotNull($registration->signature);
    }

    public function test_staff_can_upload_and_view_hand_filled_scans_but_guest_cannot_upload(): void
    {
        Storage::fake(GuestRegistration::SCANS_DISK);

        $registration = GuestRegistration::create([
            'property_id' => $this->property->id,
            'guest_id' => $this->guest->id,
            'data' => ['full_name' => 'Ada Guest'],
            'guest_name' => 'Ada Guest',
        ]);

        $this->actingAs($this->guest)
            ->post(route('guest-forms.scans.store', $registration), ['scans' => [UploadedFile::fake()->image('form.jpg')]])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('guest-forms.scans.store', $registration), ['scans' => [UploadedFile::fake()->image('form.jpg'), UploadedFile::fake()->createWithContent('form.pdf', "%PDF-1.4
1 0 obj<<>>endobj
trailer<<>>
%%EOF")]])
            ->assertRedirect(route('guest-forms.show', $registration));

        $scans = $registration->getMedia('scans');
        $this->assertCount(2, $scans);

        $this->actingAs($this->guest)->get(route('guest-forms.scans.show', [$registration, $scans->first()]))->assertOk();
        $this->actingAs($this->guest)->get(route('guest-forms.show', $registration))->assertOk()->assertSee('form.jpg');

        $this->actingAs($this->host)->delete(route('guest-forms.scans.destroy', [$registration, $scans->first()]))->assertRedirect();
        $this->assertCount(1, $registration->fresh()->getMedia('scans'));
    }

    public function test_other_users_cannot_see_a_registration(): void
    {
        $registration = GuestRegistration::create([
            'property_id' => $this->property->id,
            'guest_id' => $this->guest->id,
            'data' => ['full_name' => 'Ada Guest'],
        ]);

        $stranger = User::factory()->create();
        $stranger->assignRole('Guest');

        $this->actingAs($stranger)->get(route('guest-forms.show', $registration))->assertForbidden();
        $this->actingAs($stranger)->get(route('guest-forms.index'))->assertOk()->assertDontSee('GR-');
        $this->actingAs($this->host)->get(route('guest-forms.index'))->assertOk()->assertSee($registration->reference());
    }
}
