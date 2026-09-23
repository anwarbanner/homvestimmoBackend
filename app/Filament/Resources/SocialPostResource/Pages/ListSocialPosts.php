<?php

namespace App\Filament\Resources\SocialPostResource\Pages;

use App\Filament\Resources\SocialPostResource;
use App\Models\SocialPost;
use App\Services\SocialMedia\FacebookPublisher;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSocialPosts extends ListRecords
{
    protected static string $resource = SocialPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('rafraichir')
                ->label('Rafraîchir')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (FacebookPublisher $publisher) {
                    $posts = SocialPost::where('platform', 'facebook')
                        ->where('status', 'published')
                        ->whereNotNull('platform_post_id')
                        ->get();

                    $removed = 0;

                    foreach ($posts as $post) {
                        if (! $publisher->exists($post->platform_post_id)) {
                            $post->update(['status' => 'deleted']);
                            $removed++;
                        }
                    }

                    Notification::make()
                        ->title('Statuts vérifiés')
                        ->body("{$posts->count()} publication(s) vérifiée(s) sur Facebook, {$removed} marquée(s) comme supprimée(s).")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
