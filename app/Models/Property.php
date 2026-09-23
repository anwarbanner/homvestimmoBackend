<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory;

    /**
     * Transient, non-persisted flag set by CreateProperty when the admin
     * unchecks "Publier sur les réseaux sociaux" — read by PropertyObserver
     * to skip the automatic Facebook/Instagram publish for this save only.
     */
    public bool $skipSocialPublish = false;

    protected $fillable = [
        'reference',
        'title',
        'slug',
        'description',
        'type',
        'transaction_type',
        'rental_term',
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

    public function socialPosts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Property $property) {
            if (empty($property->slug)) {
                $property->slug = static::uniqueSlugFrom($property->title);
            }

            if (empty($property->reference)) {
                $property->reference = static::nextReference();
            }
        });

        static::saving(function (Property $property) {
            if ($property->status === 'published' && empty($property->published_at)) {
                $property->published_at = now();
            }
        });
    }

    protected static function nextReference(): string
    {
        $next = static::query()->pluck('reference')->map(fn ($reference) => (int) $reference)->max() + 1;

        while (static::where('reference', (string) $next)->exists()) {
            $next++;
        }

        return (string) $next;
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
