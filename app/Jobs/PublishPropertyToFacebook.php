<?php

namespace App\Jobs;

use App\Models\Property;
use App\Models\SocialPost;
use App\Services\SlackNotifier;
use App\Services\SocialMedia\FacebookPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class PublishPropertyToFacebook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $propertyId,
        public readonly int $socialPostId,
    ) {}

    public function handle(FacebookPublisher $publisher): void
    {
        $socialPost = SocialPost::findOrFail($this->socialPostId);

        // Guards against duplicate posts if this job is retried (explicit
        // failure retry, or re-queued by Redis after exceeding retry_after
        // while a slow multi-photo upload was still running).
        if ($socialPost->status === 'published') {
            return;
        }

        $property = Property::findOrFail($this->propertyId);

        try {
            $postId = $publisher->publish($property, $socialPost->caption);

            $socialPost->update([
                'status' => 'published',
                'platform_post_id' => $postId,
                'published_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            $socialPost->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);

            throw $e;
        }

        $this->dispatchInstagramIfNeeded($property, $socialPost);
    }

    /**
     * Instagram is only published once Facebook has succeeded, and reuses
     * the exact same images/caption that just went live on Facebook — this
     * keeps the two platforms in sync instead of racing independently.
     */
    private function dispatchInstagramIfNeeded(Property $property, SocialPost $facebookPost): void
    {
        $alreadyActive = SocialPost::where('property_id', $property->id)
            ->where('platform', 'instagram')
            ->whereIn('status', ['queued', 'published'])
            ->exists();

        if ($alreadyActive) {
            return;
        }

        $instagramPost = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'instagram',
            'status' => 'queued',
            'caption' => $facebookPost->caption,
        ]);

        PublishPropertyToInstagram::dispatch($property->id, $instagramPost->id);
    }

    public function failed(Throwable $exception): void
    {
        SocialPost::whereKey($this->socialPostId)->update([
            'status' => 'failed',
            'error_message' => Str::limit($exception->getMessage(), 500),
        ]);

        $property = Property::find($this->propertyId);

        app(SlackNotifier::class)->send(sprintf(
            "🚨 Échec définitif de la publication Facebook pour *%s* (réf %s) : %s",
            $property?->title ?? "bien #{$this->propertyId}",
            $property?->reference ?? '?',
            Str::limit($exception->getMessage(), 300),
        ));
    }
}
