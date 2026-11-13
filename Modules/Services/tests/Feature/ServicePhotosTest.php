<?php

namespace Modules\Services\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServicePhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private ServiceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach (['ServiceProvider', 'Admin', 'Guest'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->vendor = User::factory()->create();
        $this->vendor->assignRole('ServiceProvider');
        $this->category = ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'service_category_id' => $this->category->id,
            'title' => 'Express Laundry',
            'price' => 1000,
            'currency' => 'NGN',
            'audience' => 'both',
        ];
    }

    public function test_photos_are_uploaded_with_a_new_service_and_shown_on_the_site(): void
    {
        $this->actingAs($this->vendor)->post(route('provider.services.store'), $this->payload([
            'photos' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('folded.png')],
        ]))->assertSessionHasNoErrors();

        $service = Service::sole();
        $this->assertCount(2, $service->getMedia('photos'));
        $service->update(['status' => 'published']);

        $cover = $service->coverPhotoUrl();
        $this->assertStringContainsString('front', $cover);
        $this->get(route('services.index'))->assertOk()->assertSee($cover, false);
        $this->get(route('services.show', $service))->assertOk()->assertSee($cover, false)->assertSee('data-gallery', false);
    }

    public function test_edit_adds_photos_sets_the_cover_and_removes_photos(): void
    {
        $service = Service::create(['provider_id' => $this->vendor->id, 'service_category_id' => $this->category->id, 'title' => 'Laundry', 'price_amount' => 100000, 'currency' => 'NGN', 'status' => 'published']);

        $this->actingAs($this->vendor)->put(route('provider.services.update', $service), $this->payload([
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ]))->assertSessionHasNoErrors();

        [$a, $b] = $service->fresh()->getMedia('photos')->all();
        $this->actingAs($this->vendor)->get(route('provider.services.edit', $service))->assertOk()->assertSee('Make cover');

        $this->actingAs($this->vendor)->patch(route('provider.services.photos.cover', [$service, $b]))->assertRedirect();
        $this->assertSame($b->id, $service->fresh()->getFirstMedia('photos')->id);

        $this->actingAs($this->vendor)->delete(route('provider.services.photos.destroy', [$service, $b]))->assertRedirect();
        $this->assertSame([$a->id], $service->fresh()->getMedia('photos')->pluck('id')->all());
    }

    public function test_only_images_are_accepted_and_photos_stay_with_their_own_service(): void
    {
        $service = Service::create(['provider_id' => $this->vendor->id, 'service_category_id' => $this->category->id, 'title' => 'Laundry', 'price_amount' => 100000, 'currency' => 'NGN', 'status' => 'published']);
        $other = Service::create(['provider_id' => $this->vendor->id, 'service_category_id' => $this->category->id, 'title' => 'Ironing', 'price_amount' => 100000, 'currency' => 'NGN', 'status' => 'published']);

        $this->actingAs($this->vendor)->put(route('provider.services.update', $service), $this->payload([
            'photos' => [UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')],
        ]))->assertSessionHasErrors('photos.0');

        $otherPhoto = $other->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos');

        // Another service's photo can't be removed through this service's URL.
        $this->actingAs($this->vendor)->delete(route('provider.services.photos.destroy', [$service, $otherPhoto]))->assertNotFound();
        $this->assertCount(1, $other->fresh()->getMedia('photos'));

        // Someone else's vendor account can't touch them at all.
        $stranger = User::factory()->create();
        $stranger->assignRole('ServiceProvider');
        $this->actingAs($stranger)->delete(route('provider.services.photos.destroy', [$other, $otherPhoto]))->assertForbidden();
    }
}
