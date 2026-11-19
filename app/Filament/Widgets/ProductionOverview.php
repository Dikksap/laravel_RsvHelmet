<?php

namespace App\Filament\Widgets;

use App\Services\BatchProgressService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class ProductionOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $summary = app(BatchProgressService::class)->summary();
        $locale = app()->getLocale();
        $plannedQuantity = Number::format($summary['planned_quantity'], maxPrecision: 3, locale: $locale);
        $actualQuantity = Number::format($summary['actual_quantity'], maxPrecision: 3, locale: $locale);

        return [
            Stat::make('Batch berjalan', Number::format($summary['running_batches'], locale: $locale))
                ->description('Batch yang sedang diproduksi'),
            Stat::make('Rencana hasil', "{$plannedQuantity} unit")
                ->description('Total rencana batch berjalan'),
            Stat::make('Realisasi hasil', "{$actualQuantity} unit")
                ->description('Total realisasi batch berjalan'),
            Stat::make(
                'Progres total',
                Number::format($summary['progress_percentage'], maxPrecision: 1, locale: $locale).'%'
            )
                ->description("{$actualQuantity} dari {$plannedQuantity} unit"),
        ];
    }
}
