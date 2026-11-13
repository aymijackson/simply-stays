<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Titles pasted with HTML entities must show "&", not "&amp;". */
class PlainTextTitlesTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Host', 'web');
        $host = User::factory()->create();
        $host->assignRole('Host');
        $this->property = Property::create([
            'host_id' => $host->id, 'name' => 'Premium &amp; Co Suites', 'property_type' => 'apartment',
            'address' => '2 Etim Ekpo Crescent', 'city' => 'Calabar', 'country' => 'Nigeria',
        ]);
    }

    public function test_entities_are_decoded_when_saving_and_shown_once(): void
    {
        $listing = Listing::create([
            'property_id' => $this->property->id,
            'title' => 'Queen Suite – Living Room &amp;amp; Dining Bar',
            'base_price_amount' => 5000000, 'currency' => 'NGN', 'max_guests' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'status' => 'published',
        ]);

        $this->assertSame('Queen Suite – Living Room & Dining Bar', $listing->fresh()->title);
        $this->assertSame('Premium & Co Suites', $this->property->fresh()->name);

        $this->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('Living Room & Dining Bar')       // escaped once in the HTML: "&amp;"
            ->assertDontSee('&amp;amp;', false);           // never shown as the literal text "&amp;"
    }

    public function test_migration_repairs_titles_already_saved_with_entities(): void
    {
        $listing = Listing::create([
            'property_id' => $this->property->id, 'title' => 'Placeholder',
            'base_price_amount' => 5000000, 'currency' => 'NGN', 'max_guests' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'status' => 'published',
        ]);
        DB::table('listings')->where('id', $listing->id)->update(['title' => 'Living Room &amp; Dining Bar']);
        $slug = $listing->fresh()->slug;

        (require base_path('Modules/Core/database/migrations/2026_11_15_100000_decode_html_entities_in_titles.php'))->up();

        $this->assertSame('Living Room & Dining Bar', $listing->fresh()->title);
        $this->assertSame($slug, $listing->fresh()->slug, 'Existing links keep working');
    }
}
