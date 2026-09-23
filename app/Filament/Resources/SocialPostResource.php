<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SocialPostResource\Pages;
use App\Jobs\PublishPropertyToFacebook;
use App\Jobs\PublishPropertyToInstagram;
use App\Models\SocialPost;
use App\Services\SocialMedia\FacebookPublisher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class SocialPostResource extends Resource
{
    protected static ?string $model = SocialPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Publications';

    protected static ?string $modelLabel = 'publication';

    protected static ?string $pluralModelLabel = 'publications';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('property_id')
                    ->label('Bien')
                    ->relationship('property', 'title')
                    ->disabled(),
                Forms\Components\TextInput::make('platform')
                    ->label('Plateforme')
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->label('Statut')
                    ->disabled(),
                Forms\Components\Textarea::make('caption')
                    ->label('Légende')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('platform_post_id')
                    ->label('ID du post Facebook')
                    ->disabled(),
                Forms\Components\Textarea::make('error_message')
                    ->label('Erreur')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Publié le')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('property.title')
                    ->label('Bien')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('platform')
                    ->label('Plateforme'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'warning' => 'queued',
                        'success' => 'published',
                        'danger' => 'failed',
                        'secondary' => 'deleted',
                    ]),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Erreur')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Publié le')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'queued' => 'En attente',
                        'published' => 'Publié',
                        'failed' => 'Échec',
                        'deleted' => 'Supprimé',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('republier')
                    ->label('Republier')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (SocialPost $record) => $record->status === 'failed')
                    ->requiresConfirmation()
                    ->action(function (SocialPost $record) {
                        $record->update(['status' => 'queued', 'error_message' => null]);

                        if ($record->platform === 'instagram') {
                            PublishPropertyToInstagram::dispatch($record->property_id, $record->id);
                        } else {
                            PublishPropertyToFacebook::dispatch($record->property_id, $record->id);
                        }
                    }),
                Tables\Actions\Action::make('supprimerDeFacebook')
                    ->label('Supprimer de Facebook')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (SocialPost $record) => $record->platform === 'facebook' && $record->status === 'published' && $record->platform_post_id)
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer ce post de Facebook ?')
                    ->modalDescription('Le post sera supprimé définitivement de votre Page Facebook. Cette action est irréversible.')
                    ->action(function (SocialPost $record) {
                        try {
                            app(FacebookPublisher::class)->delete($record->platform_post_id);

                            $record->update(['status' => 'deleted']);

                            Notification::make()
                                ->title('Post supprimé de Facebook')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Échec de la suppression')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSocialPosts::route('/'),
            'view' => Pages\ViewSocialPost::route('/{record}'),
            'edit' => Pages\EditSocialPost::route('/{record}/edit'),
        ];
    }
}
