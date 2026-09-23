<?php

namespace Tests\Feature;

use App\Filament\Resources\PropertyResource\Pages\EditProperty;
use App\Filament\Resources\PropertyResource\RelationManagers\ImagesRelationManager;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ImagesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(Property $property)
    {
        return Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $property,
            'pageClass' => EditProperty::class,
        ]);
    }

    public function test_it_can_create_an_image(): void
    {
        Storage::fake('s3');
        $this->actingAs(User::factory()->admin()->create());
        $property = Property::factory()->create();

        $this->manager($property)
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
                'sort_order' => 0,
                'is_main' => true,
            ]);

        $image = $property->images()->first();
        $this->assertNotNull($image);
        Storage::disk('s3')->assertExists($image->path);
    }

    public function test_it_can_edit_an_image(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('properties/existing.jpg', 'fake-bytes');
        $this->actingAs(User::factory()->admin()->create());
        $property = Property::factory()->create();
        $image = $property->images()->create([
            'path' => 'properties/existing.jpg',
            'sort_order' => 0,
            'is_main' => false,
        ]);

        $this->manager($property)
            ->callTableAction('edit', $image, data: [
                'sort_order' => 3,
                'is_main' => true,
            ]);

        $image->refresh();
        $this->assertSame(3, $image->sort_order);
        $this->assertTrue($image->is_main);
    }

    public function test_it_can_delete_an_image_and_removes_the_file_from_storage(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('properties/to-delete.jpg', 'fake-bytes');
        $this->actingAs(User::factory()->admin()->create());
        $property = Property::factory()->create();
        $image = $property->images()->create([
            'path' => 'properties/to-delete.jpg',
            'sort_order' => 0,
            'is_main' => true,
        ]);

        $this->manager($property)->callTableAction('delete', $image);

        $this->assertModelMissing($image);
        Storage::disk('s3')->assertMissing('properties/to-delete.jpg');
    }
}
