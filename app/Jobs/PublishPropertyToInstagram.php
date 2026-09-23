<?php

namespace App\Jobs;

use App\Models\Property;
use App\Models\SocialPost;
use App\Services\SlackNotifier;
use App\Services\SocialMedia\InstagramPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class PublishPropertyToInstagram implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $propertyId,
        public readonly int $socialPostId,
    ) {}

    public function handle(InstagramPublisher $publisher): void
    {
        $socialPost = SocialPost::findOrFail($this->socialPostId);

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
    }

    public function failed(Throwable $exception): void
    {
        SocialPost::whereKey($this->socialPostId)->update([
            'status' => 'failed',
            'error_message' => Str::limit($exception->getMessage(), 500),
        ]);

        $property = Property::find($this->propertyId);

        app(SlackNotifier::class)->send(sprintf(
            "🚨 Échec définitif de la publication Instagram pour *%s* (réf %s) : %s",
            $property?->title ?? "bien #{$this->propertyId}",
            $property?->reference ?? '?',
            Str::limit($exception->getMessage(), 300),
        ));
    }
}
