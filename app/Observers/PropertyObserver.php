<?php

namespace App\Observers;

use App\Jobs\DeleteSocialPost;
use App\Jobs\PublishPropertyToFacebook;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\SocialPost;
use App\Services\SocialMedia\PropertyCaptionGenerator;
use Illuminate\Support\Facades\Storage;

class PropertyObserver
{
    /**
     * Handle the Property "created" event.
     */
    public function created(Property $property): void
    {
        $this->publishToFacebookIfNeeded($property);
    }

    /**
     * Handle the Property "updated" event.
     */
    public function updated(Property $property): void
    {
        $this->publishToFacebookIfNeeded($property);
    }

    private function publishToFacebookIfNeeded(Property $property): void
    {
        if (! $this->shouldPublish($property)) {
            return;
        }

        $socialPost = SocialPost::create([
            'property_id' => $property->id,
            'platform' => 'facebook',
            'status' => 'queued',
            'caption' => PropertyCaptionGenerator::make($property),
        ]);

        PublishPropertyToFacebook::dispatch($property->id, $socialPost->id);
    }

    private function shouldPublish(Property $property): bool
    {
        if ($property->skipSocialPublish) {
            return false;
        }

        if ($property->status !== 'published') {
            return false;
        }

        if (! $property->wasRecentlyCreated && ! $property->wasChanged('status')) {
            return false;
        }

        return ! SocialPost::where('property_id', $property->id)
            ->where('platform', 'facebook')
            ->whereIn('status', ['queued', 'published'])
            ->exists();
    }

    /**
     * Handle the Property "deleting" event.
     *
     * Runs before the row (and its cascade-deleted property_images /
     * social_posts) disappears, so images and platform post ids are read
     * here rather than in "deleted". A DB cascade removes the child rows
     * but never touches the actual files on S3/MinIO or the posts still
     * live on Facebook/Instagram, so both are cleaned up explicitly.
     */
    public function deleting(Property $property): void
    {
        $property->images->each(
            fn (PropertyImage $image) => Storage::disk('s3')->delete($image->path)
        );

        SocialPost::where('property_id', $property->id)
            ->whereIn('status', ['queued', 'published'])
            ->whereNotNull('platform_post_id')
            ->get(['platform', 'platform_post_id'])
            ->each(fn (SocialPost $socialPost) => DeleteSocialPost::dispatch(
                $socialPost->platform,
                $socialPost->platform_post_id,
            ));
    }

    /**
     * Handle the Property "deleted" event.
     */
    public function deleted(Property $property): void
    {
        //
    }

    /**
     * Handle the Property "restored" event.
     */
    public function restored(Property $property): void
    {
        //
    }

    /**
     * Handle the Property "force deleted" event.
     */
    public function forceDeleted(Property $property): void
    {
        //
    }
}
