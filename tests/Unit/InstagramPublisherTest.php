<?php

namespace Tests\Unit;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Services\SocialMedia\InstagramPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class InstagramPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_when_property_has_no_images(): void
    {
        $property = Property::factory()->create();
        $publisher = new InstagramPublisher('ig-account-id', 'ig-token');

        $this->expectException(RuntimeException::class);

        $publisher->publish($property, 'Une belle annonce');
    }

    public function test_publishes_single_photo_post_when_property_has_one_image(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('properties/photo.jpg', 'fake-image-bytes');

        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'creation-123'], 200),
            'graph.facebook.com/*/media_publish' => Http::response(['id' => 'ig-post-123'], 200),
        ]);

        $property = Property::factory()->create();
        PropertyImage::create([
            'property_id' => $property->id,
            'path' => 'properties/photo.jpg',
            'sort_order' => 0,
            'is_main' => true,
        ]);

        $publisher = new InstagramPublisher('ig-account-id', 'ig-token');
        $postId = $publisher->publish($property, 'Légende avec photo');

        $this->assertSame('ig-post-123', $postId);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/ig-account-id/media')
            && ! str_contains($request->url(), 'media_publish')
            && $request['caption'] === 'Légende avec photo');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/ig-account-id/media_publish')
            && $request['creation_id'] === 'creation-123');
    }

    public function test_publishes_carousel_when_property_has_multiple_images(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('properties/photo1.jpg', 'fake-image-bytes');
        Storage::disk('s3')->put('properties/photo2.jpg', 'fake-image-bytes');

        Http::fakeSequence()
            ->push(['id' => 'child-1'])
            ->push(['id' => 'child-2'])
            ->push(['id' => 'carousel-container'])
            ->push(['id' => 'ig-post-456']);

        $property = Property::factory()->create();
        PropertyImage::create(['property_id' => $property->id, 'path' => 'properties/photo1.jpg', 'sort_order' => 0, 'is_main' => true]);
        PropertyImage::create(['property_id' => $property->id, 'path' => 'properties/photo2.jpg', 'sort_order' => 1, 'is_main' => false]);

        $publisher = new InstagramPublisher('ig-account-id', 'ig-token');
        $postId = $publisher->publish($property, 'Légende carrousel');

        $this->assertSame('ig-post-456', $postId);
    }
}
