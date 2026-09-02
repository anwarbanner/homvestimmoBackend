<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Images';

    protected static ?string $modelLabel = 'image';

    protected static ?string $pluralModelLabel = 'images';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('path')
                    ->label('Fichier')
                    ->disk('s3')
                    ->directory('properties')
                    ->visibility('public')
                    ->image()
                    ->required(),
                TextInput::make('sort_order')->label('Ordre')->numeric()->default(0),
                Toggle::make('is_main')->label('Image principale'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')->label('Aperçu')->disk('s3'),
                TextColumn::make('sort_order')->label('Ordre')->sortable(),
                IconColumn::make('is_main')->label('Principale')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
