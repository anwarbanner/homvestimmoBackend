<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    protected $fillable = [
        'name',
        'file_path',
        'extension',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
