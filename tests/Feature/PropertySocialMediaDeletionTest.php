<?php

namespace Tests\Feature;

use App\Jobs\DeleteSocialPost;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertySocialMediaDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_property_removes_its_images_from_storage(): void
    {
        Queue::fake();
        Storage::fake('s3');
        Storage::disk('s3')->put('properties/photo.jpg', 'fake-image-bytes');

        $property = Property::factory()->create();
        PropertyImage::create(['property_id' => $property->id, 'path' => 'properties/photo.jpg', 'sort_order' => 0, 'is_main' => true]);

        $property->delete();

        Storage::disk('s3')->assertMissing('properties/photo.jpg');
    }

    public function test_deleting_a_property_dispatches_cleanup_for_each_published_post(): void
    {
        Queue::fake();

        $property = Property::factory()->create();

        SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'published',
            'platform_post_id' => 'fb-post-1',
        ]);

        SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'instagram',
            'status' => 'published',
            'platform_post_id' => 'ig-post-1',
        ]);

        $property->delete();

        Queue::assertPushed(DeleteSocialPost::class, fn ($job) => $job->platform === 'facebook' && $job->platformPostId === 'fb-post-1');
        Queue::assertPushed(DeleteSocialPost::class, fn ($job) => $job->platform === 'instagram' && $job->platformPostId === 'ig-post-1');
    }

    public function test_deleting_a_property_ignores_posts_without_a_platform_post_id(): void
    {
        Queue::fake();

        $property = Property::factory()->create();

        SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'instagram',
            'status' => 'queued',
        ]);

        $property->delete();

        Queue::assertNotPushed(DeleteSocialPost::class);
    }
}
