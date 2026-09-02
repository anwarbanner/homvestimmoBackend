<?php

namespace App\Filament\Widgets;

use App\Models\Property;
use Filament\Widgets\ChartWidget;

class PropertyStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Répartition par statut';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $draft = Property::where('status', 'draft')->count();
        $published = Property::where('status', 'published')->count();
        $archived = Property::where('status', 'archived')->count();

        return [
            'datasets' => [
                [
                    'data' => [$draft, $published, $archived],
                    'backgroundColor' => ['#9A9390', '#3BA55D', '#C4453B'],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => ['Brouillon', 'Publié', 'Archivé'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
