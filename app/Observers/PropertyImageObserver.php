<?php

namespace App\Observers;

use App\Models\PropertyImage;
use Illuminate\Support\Facades\Storage;

class PropertyImageObserver
{
    /**
     * Deleting a single image (e.g. via the "Images" relation manager tab)
     * only removes the DB row by default — the file would otherwise be
     * orphaned on S3/MinIO forever. Bulk deletion via a Property being
     * deleted is handled separately in PropertyObserver::deleting(), since
     * the property_images FK cascade bypasses Eloquent events entirely.
     */
    public function deleting(PropertyImage $image): void
    {
        Storage::disk('s3')->delete($image->path);
    }
}
