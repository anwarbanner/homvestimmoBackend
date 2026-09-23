<?php

namespace Tests\Feature;

use App\Jobs\PublishPropertyToFacebook;
use App\Models\Property;
use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PropertyFacebookAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_a_property_dispatches_facebook_job(): void
    {
        Queue::fake();

        $property = Property::factory()->create(['status' => 'draft']);
        $property->update(['status' => 'published']);

        Queue::assertPushed(PublishPropertyToFacebook::class, fn ($job) => $job->propertyId === $property->id);

        $this->assertDatabaseHas('social_posts', [
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'queued',
        ]);
    }

    public function test_creating_an_already_published_property_dispatches_job(): void
    {
        Queue::fake();

        $property = Property::factory()->create(['status' => 'published']);

        Queue::assertPushed(PublishPropertyToFacebook::class, fn ($job) => $job->propertyId === $property->id);
    }

    public function test_updating_unrelated_fields_does_not_redispatch(): void
    {
        Queue::fake();

        $property = Property::factory()->create(['status' => 'published']);
        Queue::assertPushedTimes(PublishPropertyToFacebook::class, 1);

        $property->update(['title' => 'Nouveau titre']);

        Queue::assertPushedTimes(PublishPropertyToFacebook::class, 1);
    }

    public function test_draft_property_does_not_dispatch_job(): void
    {
        Queue::fake();

        Property::factory()->create(['status' => 'draft']);

        Queue::assertNotPushed(PublishPropertyToFacebook::class);
    }

    public function test_it_does_not_duplicate_when_a_social_post_already_exists(): void
    {
        Queue::fake();

        $property = Property::factory()->create(['status' => 'published']);
        Queue::assertPushedTimes(PublishPropertyToFacebook::class, 1);

        $property->update(['status' => 'draft']);
        $property->update(['status' => 'published']);

        Queue::assertPushedTimes(PublishPropertyToFacebook::class, 1);
    }
}
