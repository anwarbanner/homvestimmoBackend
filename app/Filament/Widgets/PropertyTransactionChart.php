<?php

namespace App\Filament\Widgets;

use App\Models\Property;
use Filament\Widgets\ChartWidget;

class PropertyTransactionChart extends ChartWidget
{
    protected static ?string $heading = 'Vente vs Location';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $sale = Property::where('transaction_type', 'sale')->count();
        $rent = Property::where('transaction_type', 'rent')->count();

        return [
            'datasets' => [
                [
                    'data' => [$sale, $rent],
                    'backgroundColor' => ['#E5A078', '#1C1714'],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => ['À vendre', 'À louer'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
