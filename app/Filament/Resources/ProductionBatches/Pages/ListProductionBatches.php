<?php

namespace App\Filament\Resources\ProductionBatches\Pages;

use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use Filament\Actions\CreateAction;

class ListProductionBatches extends InfiniteScrollListRecords
{
    protected static string $resource = ProductionBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat batch'),
        ];
    }
}
