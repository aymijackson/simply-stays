<?php

namespace Modules\Listings\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListingDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function listing(string $description): Listing
    {
        Role::findOrCreate('Host', 'web');
        $host = User::factory()->create();
        $host->assignRole('Host');

        $property = Property::create([
            'host_id' => $host->id, 'name' => 'P', 'property_type' => 'apartment',
            'address' => '1 St', 'city' => 'Calabar', 'country' => 'Nigeria',
        ]);

        return Listing::create([
            'property_id' => $property->id, 'title' => 'Suite', 'slug' => 'suite',
            'description' => $description,
            'base_price_amount' => 100000, 'currency' => 'NGN', 'max_guests' => 2,
            'bedrooms' => 1, 'bathrooms' => 1, 'status' => 'published',
        ]);
    }

    public function test_rich_description_is_sanitised_on_save_and_rendered_as_html(): void
    {
        $listing = $this->listing('<h2>Welcome</h2><p onclick="steal()">Cosy <strong>suite</strong><script>alert(1)</script></p><ul><li>Wi-Fi</li></ul><a href="javascript:alert(1)">bad</a>');

        $this->assertStringNotContainsString('script', $listing->description);
        $this->assertStringNotContainsString('onclick', $listing->description);
        $this->assertStringNotContainsString('javascript:', $listing->description);

        $this->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('<h2>Welcome</h2>', false)
            ->assertSee('<strong>suite</strong>', false)
            ->assertSee('<li>Wi-Fi</li>', false)
            ->assertDontSee('alert(1)', false);
    }

    public function test_legacy_plain_text_description_is_escaped_into_paragraphs(): void
    {
        $listing = $this->listing("Tom & Jerry's place\n\nQuiet <street>");

        $this->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('<p>Tom &amp; Jerry&#039;s place</p>', false)
            ->assertSee('<p>Quiet &lt;street&gt;</p>', false);
    }

    public function test_edit_form_uses_the_rich_editor(): void
    {
        $listing = $this->listing('<p>Hello</p>');

        $this->actingAs($listing->property->host)
            ->get(route('host.listings.edit', $listing))
            ->assertOk()
            ->assertSee('data-rich-editor', false)
            ->assertSee('quill@2.0.3', false);
    }
}
