<?php

namespace App\Services\SocialMedia;

use App\Models\Property;
use Illuminate\Support\Str;

class PropertyCaptionGenerator
{
    public static function make(Property $property): string
    {
        $price = number_format((float) $property->price, 0, ',', ' ').' DH';
        $transaction = $property->transaction_type === 'rent' ? 'À louer' : 'À vendre';

        $lines = [
            "{$transaction} : ".self::typeLabel($property->type)." à {$property->city}",
            $property->title,
            '',
            '💰 '.$price.($property->transaction_type === 'rent' ? ' / mois' : ''),
            "📐 {$property->surface} m²",
        ];

        if ($property->transaction_type === 'rent' && $property->rental_term) {
            $lines[] = '📅 '.self::rentalTermLabel($property->rental_term);
        }

        if ($property->bedrooms > 0) {
            $lines[] = "🛏️ {$property->bedrooms} chambre(s)";
        }

        if ($property->bathrooms > 0) {
            $lines[] = "🚿 {$property->bathrooms} salle(s) de bain";
        }

        $lines[] = '';
        $lines[] = "📍 {$property->address}, {$property->city}";

        if ($property->description) {
            $lines[] = '';
            $lines[] = Str::limit($property->description, 400);
        }

        $lines[] = '';
        $lines[] = "Réf: {$property->reference}";

        return implode("\n", $lines);
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'apartment' => 'Appartement',
            'villa' => 'Villa',
            'house' => 'Maison',
            'office' => 'Bureau',
            'land' => 'Terrain',
            'commercial' => 'Local commercial',
            default => 'Bien',
        };
    }

    private static function rentalTermLabel(string $rentalTerm): string
    {
        return match ($rentalTerm) {
            'short_term' => 'Courte durée',
            'long_term' => 'Longue durée',
            default => $rentalTerm,
        };
    }
}
