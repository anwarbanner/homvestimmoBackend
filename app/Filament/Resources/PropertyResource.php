<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyResource\Pages;
use App\Filament\Resources\PropertyResource\RelationManagers\ImagesRelationManager;
use App\Models\Property;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Biens';

    protected static ?string $modelLabel = 'bien';

    protected static ?string $pluralModelLabel = 'biens';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')->label('Titre')->required()->maxLength(255),
                TextInput::make('reference')->label('Référence')->required()->unique(ignoreRecord: true),
                Select::make('type')
                    ->label('Type')
                    ->options([
                        'apartment' => 'Appartement',
                        'villa' => 'Villa',
                        'house' => 'Maison',
                        'office' => 'Bureau',
                        'land' => 'Terrain',
                        'commercial' => 'Commercial',
                        'other' => 'Autre',
                    ])->required(),
                Select::make('transaction_type')
                    ->label('Type de transaction')
                    ->options([
                        'sale' => 'Vente',
                        'rent' => 'Location',
                    ])->required(),
                Select::make('status')
                    ->label('Statut')
                    ->options([
                        'draft' => 'Brouillon',
                        'published' => 'Publié',
                        'archived' => 'Archivé',
                    ])->default('draft')->required(),
                TextInput::make('price')->label('Prix')->numeric()->suffix('DH')->required(),
                TextInput::make('surface')->label('Surface')->numeric()->suffix('m²')->required(),
                TextInput::make('bedrooms')->label('Chambres')->numeric()->default(0),
                TextInput::make('bathrooms')->label('Salles de bain')->numeric()->default(0),
                TextInput::make('address')->label('Adresse')->required(),
                TextInput::make('city')->label('Ville')->required(),
                TextInput::make('latitude')->label('Latitude')->numeric(),
                TextInput::make('longitude')->label('Longitude')->numeric(),
                Toggle::make('featured')->label('Mis en avant'),
                DateTimePicker::make('published_at')->label('Publié le'),
                RichEditor::make('description')->label('Description')->columnSpanFull(),
                FileUpload::make('new_images')
                    ->label('Images')
                    ->disk('s3')
                    ->directory('properties')
                    ->visibility('public')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label('Référence')->sortable()->searchable(),
                TextColumn::make('title')->label('Titre')->limit(30)->searchable(),
                BadgeColumn::make('type')->label('Type'),
                BadgeColumn::make('transaction_type')->label('Transaction')->colors([
                    'success' => 'sale',
                    'warning' => 'rent',
                ]),
                BadgeColumn::make('status')->label('Statut')->colors([
                    'secondary' => 'draft',
                    'success' => 'published',
                    'danger' => 'archived',
                ]),
                TextColumn::make('price')->label('Prix')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' ').' DH')
                    ->sortable(),
                TextColumn::make('city')->label('Ville')->sortable(),
                IconColumn::make('featured')->label('Mis en avant')->boolean(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProperties::route('/'),
            'create' => Pages\CreateProperty::route('/create'),
            'edit' => Pages\EditProperty::route('/{record}/edit'),
        ];
    }
}
