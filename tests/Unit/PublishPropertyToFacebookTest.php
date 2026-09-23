<?php

namespace Tests\Unit;

use App\Jobs\PublishPropertyToFacebook;
use App\Jobs\PublishPropertyToInstagram;
use App\Models\Property;
use App\Models\SocialPost;
use App\Services\SocialMedia\FacebookPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PublishPropertyToFacebookTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_republish_when_the_social_post_is_already_published(): void
    {
        Http::fake(function () {
            $this->fail('The Graph API should not be called for an already-published post.');
        });

        $property = Property::factory()->create();
        $socialPost = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'published',
            'platform_post_id' => 'already-there',
        ]);

        (new PublishPropertyToFacebook($property->id, $socialPost->id))->handle(
            app(FacebookPublisher::class)
        );

        $this->assertSame('already-there', $socialPost->fresh()->platform_post_id);
    }

    public function test_it_dispatches_instagram_only_after_facebook_succeeds(): void
    {
        Queue::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => 'fb-post-1'], 200),
        ]);

        $property = Property::factory()->create();
        $socialPost = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'queued',
            'caption' => 'Une belle annonce',
        ]);

        (new PublishPropertyToFacebook($property->id, $socialPost->id))->handle(
            app(FacebookPublisher::class)
        );

        Queue::assertPushed(PublishPropertyToInstagram::class, fn ($job) => $job->propertyId === $property->id);

        $this->assertDatabaseHas('social_posts', [
            'property_id' => $property->id,
            'platform' => 'instagram',
            'status' => 'queued',
            'caption' => 'Une belle annonce',
        ]);
    }

    public function test_it_does_not_dispatch_instagram_when_facebook_publish_fails(): void
    {
        Queue::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'boom']], 400),
        ]);

        $property = Property::factory()->create();
        $socialPost = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'queued',
            'caption' => 'Une belle annonce',
        ]);

        try {
            (new PublishPropertyToFacebook($property->id, $socialPost->id))->handle(
                app(FacebookPublisher::class)
            );
        } catch (\Throwable) {
            // expected: the Graph API call fails
        }

        Queue::assertNotPushed(PublishPropertyToInstagram::class);
        $this->assertDatabaseMissing('social_posts', ['platform' => 'instagram']);
    }

    public function test_it_does_not_duplicate_instagram_dispatch_when_already_active(): void
    {
        Queue::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => 'fb-post-1'], 200),
        ]);

        $property = Property::factory()->create();
        SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'instagram',
            'status' => 'published',
            'platform_post_id' => 'ig-post-existing',
        ]);
        $socialPost = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'queued',
            'caption' => 'Une belle annonce',
        ]);

        (new PublishPropertyToFacebook($property->id, $socialPost->id))->handle(
            app(FacebookPublisher::class)
        );

        Queue::assertNotPushed(PublishPropertyToInstagram::class);
    }

    public function test_failed_sends_a_slack_alert_when_slack_is_configured(): void
    {
        Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-fake-token');
        Config::set('services.slack.notifications.channel', '#alerts');

        Http::fake([
            'slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        $property = Property::factory()->create(['title' => 'Villa Alerte']);
        $socialPost = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'queued',
        ]);

        (new PublishPropertyToFacebook($property->id, $socialPost->id))
            ->failed(new RuntimeException('boom'));

        $this->assertSame('failed', $socialPost->fresh()->status);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'slack.com')
            && str_contains($request['text'], 'Villa Alerte')
            && str_contains($request['text'], 'boom'));
    }
}
