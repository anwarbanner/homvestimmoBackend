<?php

namespace Tests\Feature;

use App\Filament\Resources\PropertyResource\Pages\CreateProperty;
use App\Filament\Resources\PropertyResource\Pages\EditProperty;
use App\Filament\Resources\PropertyResource\Pages\ListProperties;
use App\Jobs\PublishPropertyToFacebook;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyAdminEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_property_with_images_through_the_admin_stores_everything_and_triggers_publish(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateProperty::class)
            ->fillForm([
                'title' => 'Villa E2E Admin',
                'type' => 'villa',
                'transaction_type' => 'rent',
                'rental_term' => 'long_term',
                'status' => 'published',
                'price' => 12000,
                'surface' => 180,
                'address' => '12 Rue du Test',
                'city' => 'Marrakech',
                'new_images' => [
                    UploadedFile::fake()->create('photo1.jpg', 100, 'image/jpeg'),
                    UploadedFile::fake()->create('photo2.jpg', 100, 'image/jpeg'),
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $property = Property::where('title', 'Villa E2E Admin')->firstOrFail();

        // reference auto-generated
        $this->assertNotEmpty($property->reference);
        // published_at auto-set because status was submitted as "published"
        $this->assertNotNull($property->published_at);
        // rental_term correctly stored for a "location" property
        $this->assertSame('long_term', $property->rental_term);
        // both uploaded images are attached and physically present on the fake disk
        $this->assertCount(2, $property->images);
        foreach ($property->images as $image) {
            Storage::disk('s3')->assertExists($image->path);
        }

        Queue::assertPushed(PublishPropertyToFacebook::class, fn ($job) => $job->propertyId === $property->id);
    }

    public function test_unchecking_publish_to_social_skips_the_facebook_dispatch(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateProperty::class)
            ->fillForm([
                'title' => 'Villa Sans Reseaux',
                'type' => 'villa',
                'transaction_type' => 'sale',
                'status' => 'published',
                'price' => 12000,
                'surface' => 180,
                'address' => '12 Rue du Test',
                'city' => 'Marrakech',
                'publish_to_social' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $property = Property::where('title', 'Villa Sans Reseaux')->firstOrFail();

        Queue::assertNotPushed(PublishPropertyToFacebook::class);
    }

    public function test_publish_to_social_defaults_to_true(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateProperty::class)
            ->fillForm([
                'title' => 'Villa Avec Reseaux',
                'type' => 'villa',
                'transaction_type' => 'sale',
                'status' => 'published',
                'price' => 12000,
                'surface' => 180,
                'address' => '12 Rue du Test',
                'city' => 'Marrakech',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $property = Property::where('title', 'Villa Avec Reseaux')->firstOrFail();

        Queue::assertPushed(PublishPropertyToFacebook::class, fn ($job) => $job->propertyId === $property->id);
    }

    public function test_images_field_is_only_shown_on_the_create_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $property = Property::factory()->create();

        Livewire::test(CreateProperty::class)
            ->assertFormFieldIsVisible('new_images');

        Livewire::test(EditProperty::class, ['record' => $property->getRouteKey()])
            ->assertFormFieldIsHidden('new_images');
    }

    public function test_deleting_a_property_from_the_admin_list_removes_its_images_from_storage(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $this->actingAs(User::factory()->admin()->create());

        Storage::disk('s3')->put('properties/e2e-photo.jpg', 'fake-bytes');
        $property = Property::factory()->create();
        $property->images()->create([
            'path' => 'properties/e2e-photo.jpg',
            'sort_order' => 0,
            'is_main' => true,
        ]);

        Livewire::test(ListProperties::class)
            ->callTableBulkAction('delete', [$property]);

        $this->assertModelMissing($property);
        Storage::disk('s3')->assertMissing('properties/e2e-photo.jpg');
    }
}
