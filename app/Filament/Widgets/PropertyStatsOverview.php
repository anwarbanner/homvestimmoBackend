<?php

namespace App\Filament\Widgets;

use App\Models\Property;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PropertyStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = Property::count();
        $published = Property::where('status', 'published')->count();
        $draft = Property::where('status', 'draft')->count();
        $totalValue = Property::where('status', 'published')->sum('price');
        $avgPrice = Property::where('status', 'published')->avg('price');

        $money = fn ($n) => number_format((float) $n, 0, ',', ' ').' DH';

        return [
            Stat::make('Total des biens', $total)
                ->description($draft.' en brouillon')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('gray'),

            Stat::make('Biens publiés', $published)
                ->description($total > 0 ? round($published / $total * 100).'% du portefeuille' : 'Aucun bien')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Valeur du portefeuille', $money($totalValue))
                ->description('Somme des biens publiés')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Prix moyen', $money($avgPrice ?? 0))
                ->description('Biens publiés')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary'),
        ];
    }
}
