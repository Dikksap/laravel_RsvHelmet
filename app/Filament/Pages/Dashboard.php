<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dasbor';

    public function getSubheading(): ?string
    {
        return 'Progres batch produksi';
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reports')
                ->label('Laporan realisasi')
                ->icon('heroicon-o-chart-bar')
                ->url(BatchRealization::getUrl()),
            Action::make('createBatch')
                ->label('Batch baru')
                ->icon('heroicon-o-plus')
                ->url(ProductionBatchResource::getUrl('create')),
        ];
    }
}
