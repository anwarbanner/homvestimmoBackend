<?php

namespace App\Services\SocialMedia;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FacebookPublisher
{
    private const API_VERSION = 'v20.0';

    private ?string $resolvedPageAccessToken = null;

    public function __construct(
        private readonly string $pageId,
        private readonly string $pageToken,
    ) {}

    public function publish(Property $property, string $caption): string
    {
        $imagePaths = $property->images()->orderBy('sort_order')->pluck('path');

        if ($imagePaths->isEmpty()) {
            return $this->publishTextOnly($caption);
        }

        if ($imagePaths->count() === 1) {
            return $this->publishSinglePhoto($imagePaths->first(), $caption);
        }

        return $this->publishMultiplePhotos($imagePaths, $caption);
    }

    private function publishTextOnly(string $caption): string
    {
        $response = $this->request()->asForm()->post("/{$this->pageId}/feed", [
            'message' => $caption,
        ]);

        return $response->json('id');
    }

    private function publishSinglePhoto(string $path, string $caption): string
    {
        $response = $this->request()
            ->attach('source', $this->fetchImage($path), basename($path))
            ->post("/{$this->pageId}/photos", [
                'caption' => $caption,
                'published' => 'true',
            ]);

        return $response->json('post_id') ?? $response->json('id');
    }

    private function publishMultiplePhotos(Collection $paths, string $caption): string
    {
        $mediaFbids = $paths->map(function (string $path) {
            $response = $this->request()
                ->attach('source', $this->fetchImage($path), basename($path))
                ->post("/{$this->pageId}/photos", [
                    'published' => 'false',
                ]);

            return $response->json('id');
        });

        $response = $this->request()->asForm()->post("/{$this->pageId}/feed", [
            'message' => $caption,
            'attached_media' => $mediaFbids
                ->map(fn (string $id) => json_encode(['media_fbid' => $id]))
                ->all(),
        ]);

        return $response->json('id');
    }

    public function delete(string $platformPostId): void
    {
        $this->request()->asForm()->delete("/{$platformPostId}", [
            'access_token' => $this->pageAccessToken(),
        ]);
    }

    /**
     * Checks whether a post still exists on Facebook, so a locally
     * "published" record can be reconciled if it was removed directly
     * on Facebook/Instagram outside of this application.
     */
    public function exists(string $platformPostId): bool
    {
        return Http::baseUrl('https://graph.facebook.com/'.self::API_VERSION)
            ->withToken($this->pageAccessToken())
            ->get("/{$platformPostId}", ['fields' => 'id'])
            ->successful();
    }

    /**
     * Publishing and deleting page content both require a genuine Page
     * access token; a System User token (used to configure this service)
     * is rejected by the Graph API for these page-scoped operations, so it
     * must be exchanged once for the page's own token and reused.
     */
    private function pageAccessToken(): string
    {
        if ($this->resolvedPageAccessToken !== null) {
            return $this->resolvedPageAccessToken;
        }

        $response = Http::baseUrl('https://graph.facebook.com/'.self::API_VERSION)
            ->withToken($this->pageToken)
            ->throw()
            ->get("/{$this->pageId}", ['fields' => 'access_token']);

        return $this->resolvedPageAccessToken = $response->json('access_token') ?? $this->pageToken;
    }

    private function fetchImage(string $path): string
    {
        $contents = Storage::disk('s3')->get($path);

        if ($contents === null) {
            throw new RuntimeException("Image introuvable sur le disque S3 : {$path}");
        }

        return $contents;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl('https://graph.facebook.com/'.self::API_VERSION)
            ->withToken($this->pageAccessToken())
            ->throw();
    }
}
