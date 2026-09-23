<?php

namespace Tests\Unit;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Services\SocialMedia\FacebookPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacebookPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_text_only_post_when_property_has_no_images(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => 'page_123_456'], 200),
        ]);

        $property = Property::factory()->create();
        $publisher = new FacebookPublisher('page-id', 'page-token');

        $postId = $publisher->publish($property, 'Une belle annonce');

        $this->assertSame('page_123_456', $postId);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/page-id/feed')
                && $request['message'] === 'Une belle annonce';
        });
    }

    public function test_publishes_single_photo_post_when_property_has_one_image(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('properties/photo.jpg', 'fake-image-bytes');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['post_id' => 'page_123_photo'], 200),
        ]);

        $property = Property::factory()->create();
        PropertyImage::create([
            'property_id' => $property->id,
            'path' => 'properties/photo.jpg',
            'sort_order' => 0,
            'is_main' => true,
        ]);

        $publisher = new FacebookPublisher('page-id', 'page-token');
        $postId = $publisher->publish($property, 'Légende avec photo');

        $this->assertSame('page_123_photo', $postId);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/page-id/photos'));
    }

    public function test_delete_calls_the_graph_api_with_the_post_id(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true], 200),
        ]);

        $publisher = new FacebookPublisher('page-id', 'page-token');
        $publisher->delete('page-id_post-id');

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/page-id_post-id'));
    }
}
