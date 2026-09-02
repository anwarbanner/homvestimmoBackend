<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'title',
        'slug',
        'description',
        'type',
        'transaction_type',
        'price',
        'surface',
        'bedrooms',
        'bathrooms',
        'address',
        'city',
        'latitude',
        'longitude',
        'status',
        'featured',
        'published_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function socialPosts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    protected static function booted(): void
    {
        static::creating(function (Property $property) {
            if (empty($property->slug)) {
                $property->slug = static::uniqueSlugFrom($property->title);
            }
        });
    }

    protected static function uniqueSlugFrom(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
