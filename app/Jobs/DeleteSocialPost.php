<?php

namespace App\Jobs;

use App\Services\SocialMedia\FacebookPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteSocialPost implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $platform,
        public readonly string $platformPostId,
    ) {}

    public function handle(FacebookPublisher $facebookPublisher): void
    {
        if ($this->platform === 'facebook') {
            $facebookPublisher->delete($this->platformPostId);
        }

        // Meta's Instagram Content Publishing API does not support deleting
        // an already-published post under any permission set (confirmed by
        // testing) — it has to be removed manually in the Instagram app.
    }
}
