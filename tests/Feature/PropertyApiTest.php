<?php

namespace Tests\Feature;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Creating/publishing properties triggers PropertyObserver's social
        // media dispatch; these tests only care about the public API shape.
        Queue::fake();
    }

    public function test_index_only_returns_published_properties(): void
    {
        Property::factory()->create(['status' => 'published', 'title' => 'Villa publiée']);
        Property::factory()->create(['status' => 'draft', 'title' => 'Villa brouillon']);
        Property::factory()->create(['status' => 'archived', 'title' => 'Villa archivée']);

        $response = $this->getJson('/api/properties')->assertSuccessful();

        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Villa publiée'));
        $this->assertFalse($titles->contains('Villa brouillon'));
        $this->assertFalse($titles->contains('Villa archivée'));
    }

    public function test_index_filters_by_transaction_type(): void
    {
        Property::factory()->create(['status' => 'published', 'transaction_type' => 'sale', 'title' => 'A vendre']);
        Property::factory()->create(['status' => 'published', 'transaction_type' => 'rent', 'title' => 'A louer']);

        $response = $this->getJson('/api/properties?transactionType=rent')->assertSuccessful();

        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('A louer'));
        $this->assertFalse($titles->contains('A vendre'));
    }

    public function test_index_paginates_and_caps_per_page_at_48(): void
    {
        Property::factory()->count(3)->create(['status' => 'published']);

        $response = $this->getJson('/api/properties?per_page=1000')->assertSuccessful();

        $response->assertJsonPath('meta.perPage', 48);
        $response->assertJsonStructure([
            'data',
            'meta' => ['currentPage', 'lastPage', 'perPage', 'total'],
        ]);
    }

    public function test_index_includes_ordered_images_with_public_urls(): void
    {
        Storage::fake('s3');

        $property = Property::factory()->create(['status' => 'published']);
        $property->images()->create(['path' => 'properties/second.jpg', 'sort_order' => 1, 'is_main' => false]);
        $property->images()->create(['path' => 'properties/first.jpg', 'sort_order' => 0, 'is_main' => true]);

        $response = $this->getJson('/api/properties')->assertSuccessful();

        $images = collect($response->json('data'))->firstWhere('id', $property->id)['images'];

        $this->assertCount(2, $images);
        $this->assertStringContainsString('first.jpg', $images[0]['url']);
        $this->assertTrue($images[0]['isMain']);
        $this->assertStringContainsString('second.jpg', $images[1]['url']);
    }

    public function test_show_returns_full_detail_for_a_published_property(): void
    {
        $property = Property::factory()->create([
            'status' => 'published',
            'description' => 'Une très belle propriété.',
            'latitude' => 31.6295,
            'longitude' => -7.9811,
        ]);

        $response = $this->getJson("/api/properties/{$property->id}")->assertSuccessful();

        $response->assertJsonPath('data.id', $property->id);
        $response->assertJsonPath('data.description', 'Une très belle propriété.');
        $response->assertJsonPath('data.latitude', 31.6295);
        $response->assertJsonPath('data.longitude', -7.9811);
    }

    public function test_show_returns_404_for_a_draft_property(): void
    {
        $property = Property::factory()->create(['status' => 'draft']);

        $this->getJson("/api/properties/{$property->id}")->assertNotFound();
    }

    public function test_show_returns_404_for_a_missing_property(): void
    {
        $this->getJson('/api/properties/999999')->assertNotFound();
    }
}
