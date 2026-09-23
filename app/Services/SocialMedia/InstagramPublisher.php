<?php

namespace App\Services\SocialMedia;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InstagramPublisher
{
    private const API_VERSION = 'v20.0';

    public function __construct(
        private readonly string $businessAccountId,
        private readonly string $accessToken,
    ) {}

    public function publish(Property $property, string $caption): string
    {
        $imagePaths = $property->images()->orderBy('sort_order')->pluck('path');

        if ($imagePaths->isEmpty()) {
            throw new RuntimeException('Instagram nécessite au moins une image pour publier une annonce.');
        }

        if ($imagePaths->count() === 1) {
            return $this->publishSinglePhoto($imagePaths->first(), $caption);
        }

        return $this->publishCarousel($imagePaths, $caption);
    }

    private function publishSinglePhoto(string $path, string $caption): string
    {
        $creationId = $this->createMediaContainer([
            'image_url' => $this->publicUrl($path),
            'caption' => $caption,
        ]);

        return $this->publishContainer($creationId);
    }

    private function publishCarousel(Collection $paths, string $caption): string
    {
        $childrenIds = $paths->map(fn (string $path) => $this->createMediaContainer([
            'image_url' => $this->publicUrl($path),
            'is_carousel_item' => 'true',
        ]));

        $creationId = $this->createMediaContainer([
            'media_type' => 'CAROUSEL',
            'children' => $childrenIds->implode(','),
            'caption' => $caption,
        ]);

        return $this->publishContainer($creationId);
    }

    private function createMediaContainer(array $params): string
    {
        $response = $this->request()->asForm()->post("/{$this->businessAccountId}/media", $params);

        return $response->json('id');
    }

    private function publishContainer(string $creationId): string
    {
        $response = $this->request()->asForm()->post("/{$this->businessAccountId}/media_publish", [
            'creation_id' => $creationId,
        ]);

        return $response->json('id');
    }

    private function publicUrl(string $path): string
    {
        return Storage::disk('s3')->url($path);
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl('https://graph.facebook.com/'.self::API_VERSION)
            ->withToken($this->accessToken)
            ->throw();
    }
}
