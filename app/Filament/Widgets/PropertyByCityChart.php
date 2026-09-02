<?php

namespace App\Filament\Widgets;

use App\Models\Property;
use Filament\Widgets\ChartWidget;

class PropertyByCityChart extends ChartWidget
{
    protected static ?string $heading = 'Biens par ville';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $rows = Property::query()
            ->selectRaw('city, count(*) as total')
            ->groupBy('city')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Biens',
                    'data' => $rows->pluck('total')->all(),
                    'backgroundColor' => '#E5A078',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $rows->pluck('city')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1]],
            ],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
