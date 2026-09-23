<?php

namespace Tests\Feature;

use App\Filament\Resources\SocialPostResource\Pages\ListSocialPosts;
use App\Jobs\PublishPropertyToFacebook;
use App\Jobs\PublishPropertyToInstagram;
use App\Models\Property;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SocialPostResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_from_facebook_action_is_hidden_for_instagram_posts(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $property = Property::factory()->create();

        $facebookPost = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'published',
            'platform_post_id' => 'fb-post-1',
        ]);

        $instagramPost = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'instagram',
            'status' => 'published',
            'platform_post_id' => 'ig-post-1',
        ]);

        Livewire::test(ListSocialPosts::class)
            ->assertTableActionVisible('supprimerDeFacebook', $facebookPost)
            ->assertTableActionHidden('supprimerDeFacebook', $instagramPost);
    }

    public function test_republish_dispatches_the_facebook_job_for_a_failed_facebook_post(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->admin()->create());
        $property = Property::factory()->create();

        $post = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'failed',
            'error_message' => 'boom',
        ]);

        Livewire::test(ListSocialPosts::class)
            ->callTableAction('republier', $post);

        $this->assertSame('queued', $post->fresh()->status);
        $this->assertNull($post->fresh()->error_message);
        Queue::assertPushed(PublishPropertyToFacebook::class, fn ($job) => $job->socialPostId === $post->id);
    }

    public function test_republish_dispatches_the_instagram_job_for_a_failed_instagram_post(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->admin()->create());
        $property = Property::factory()->create();

        $post = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'instagram',
            'status' => 'failed',
            'error_message' => 'boom',
        ]);

        Livewire::test(ListSocialPosts::class)
            ->callTableAction('republier', $post);

        $this->assertSame('queued', $post->fresh()->status);
        Queue::assertPushed(PublishPropertyToInstagram::class, fn ($job) => $job->socialPostId === $post->id);
        Queue::assertNotPushed(PublishPropertyToFacebook::class);
    }

    public function test_delete_from_facebook_action_marks_the_post_as_deleted(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $property = Property::factory()->create();

        $post = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'published',
            'platform_post_id' => 'fb-to-delete',
        ]);

        Http::fakeSequence()
            ->push(['access_token' => 'resolved-page-token']) // page token resolution
            ->push(['success' => true]); // delete call

        Livewire::test(ListSocialPosts::class)
            ->callTableAction('supprimerDeFacebook', $post);

        $this->assertSame('deleted', $post->fresh()->status);
    }

    public function test_refresh_action_marks_a_post_as_deleted_when_facebook_no_longer_has_it(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $property = Property::factory()->create();

        $stillThere = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'published',
            'platform_post_id' => 'fb-still-here',
        ]);

        $goneFromFacebook = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'published',
            'platform_post_id' => 'fb-gone',
        ]);

        Http::fake([
            'graph.facebook.com/*/test-page-id*' => Http::response(['access_token' => 'resolved-page-token']),
            'graph.facebook.com/*/fb-still-here*' => Http::response(['id' => 'fb-still-here'], 200),
            'graph.facebook.com/*/fb-gone*' => Http::response(['error' => ['message' => 'gone']], 400),
        ]);

        Livewire::test(ListSocialPosts::class)
            ->callAction('rafraichir');

        $this->assertSame('published', $stillThere->fresh()->status);
        $this->assertSame('deleted', $goneFromFacebook->fresh()->status);
    }
}
