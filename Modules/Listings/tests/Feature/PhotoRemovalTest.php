<?php

namespace Modules\Listings\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhotoRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_edit_page_has_no_nested_forms_and_removing_a_photo_keeps_the_listing(): void
    {
        Storage::fake('public');
        Role::findOrCreate('Host', 'web');
        $host = User::factory()->create();
        $host->assignRole('Host');

        $property = Property::create([
            'host_id' => $host->id, 'name' => 'P', 'property_type' => 'apartment',
            'address' => '1 St', 'city' => 'Calabar', 'country' => 'Nigeria',
        ]);
        $listing = Listing::create([
            'property_id' => $property->id, 'title' => 'Suite', 'slug' => 'suite',
            'base_price_amount' => 100000, 'currency' => 'NGN', 'max_guests' => 2,
            'bedrooms' => 1, 'bathrooms' => 1, 'status' => 'published',
        ]);
        $media = $listing->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('photos');

        $html = $this->actingAs($host)->get(route('host.listings.edit', $listing))->assertOk()->getContent();

        // The update form must close before any photo-delete form opens.
        $updateStart = strpos($html, 'action="'.route('host.listings.update', $listing).'"');
        $updateEnd = strpos($html, '</form>', $updateStart);
        $deleteForm = strpos($html, 'id="photo-delete-'.$media->id.'"');
        $this->assertNotFalse($deleteForm);
        $this->assertGreaterThan($updateEnd, $deleteForm);
        $this->assertStringContainsString('form="photo-delete-'.$media->id.'"', $html);

        $this->delete(route('host.listings.photos.destroy', [$listing, $media]))
            ->assertRedirect(route('host.listings.edit', $listing));

        $this->assertModelExists($listing);
        $this->assertCount(0, $listing->fresh()->getMedia('photos'));
    }
}
