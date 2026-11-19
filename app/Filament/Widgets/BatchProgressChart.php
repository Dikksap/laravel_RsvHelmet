<?php

namespace App\Filament\Widgets;

use App\Services\BatchProgressService;
use Filament\Widgets\ChartWidget;

class BatchProgressChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Progres tiap batch';

    protected ?string $description = 'Rencana vs realisasi hasil batch yang sedang berjalan.';

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $batches = app(BatchProgressService::class)->runningBatches();

        return [
            'labels' => array_column($batches, 'batch_code'),
            'datasets' => [
                [
                    'label' => 'Rencana',
                    'data' => array_column($batches, 'planned_quantity'),
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'Realisasi',
                    'data' => array_column($batches, 'actual_quantity'),
                    'backgroundColor' => '#10b981',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
