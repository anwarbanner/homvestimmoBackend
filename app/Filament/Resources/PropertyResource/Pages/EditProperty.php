<?php

namespace App\Filament\Resources\PropertyResource\Pages;

use App\Filament\Resources\PropertyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $paths = array_values($this->form->getRawState()['new_images'] ?? []);

        if (empty($paths)) {
            return;
        }

        $nextSortOrder = ((int) $this->record->images()->max('sort_order')) + 1;
        $hasMainImage = $this->record->images()->where('is_main', true)->exists();

        foreach ($paths as $index => $path) {
            $this->record->images()->create([
                'path' => $path,
                'sort_order' => $nextSortOrder + $index,
                'is_main' => (! $hasMainImage) && $index === 0,
            ]);
        }
    }
}
