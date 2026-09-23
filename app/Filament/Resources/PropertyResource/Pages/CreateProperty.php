<?php

namespace App\Filament\Resources\PropertyResource\Pages;

use App\Filament\Resources\PropertyResource;
use App\Models\Property;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    /**
     * The "publish_to_social" toggle isn't a real column: it must be read
     * and removed here, before the model is saved, so PropertyObserver
     * (which fires on the model's "created" event) can see the flag.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $publishToSocial = (bool) ($data['publish_to_social'] ?? true);
        unset($data['publish_to_social']);

        $record = new Property($data);
        $record->skipSocialPublish = ! $publishToSocial;
        $record->save();

        return $record;
    }

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
