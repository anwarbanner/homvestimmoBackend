<?php

namespace App\Filament\Resources\PropertyResource\Pages;

use App\Filament\Resources\PropertyResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    protected function afterCreate(): void
    {
        $paths = array_values($this->form->getRawState()['new_images'] ?? []);

        foreach ($paths as $index => $path) {
            $this->record->images()->create([
                'path' => $path,
                'sort_order' => $index,
                'is_main' => $index === 0,
            ]);
        }
    }
}
