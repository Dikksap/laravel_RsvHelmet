<?php

namespace App\Filament\Widgets;

use App\Models\ProductionBatch;
use App\Services\BatchProgressService;
use Filament\Widgets\ChartWidget;

class BatchStatusChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Status batch';

    protected ?string $description = 'Distribusi batch berdasarkan status.';

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $statusCounts = app(BatchProgressService::class)->statusCounts();

        return [
            'labels' => array_values(ProductionBatch::statusOptions()),
            'datasets' => [
                [
                    'data' => array_values($statusCounts),
                    'backgroundColor' => [
                        '#9ca3af',
                        '#3b82f6',
                        '#10b981',
                        '#ef4444',
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
