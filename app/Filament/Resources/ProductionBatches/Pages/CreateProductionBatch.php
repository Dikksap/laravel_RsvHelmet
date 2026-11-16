<?php

namespace App\Filament\Resources\ProductionBatches\Pages;

use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Models\ProductionBatch;
use App\Services\BatchRequirementService;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionBatch extends CreateRecord
{
    protected static string $resource = ProductionBatchResource::class;

    protected static ?string $title = 'Buat Batch Produksi';

    protected function afterCreate(): void
    {
        app(BatchRequirementService::class)->recalculate($this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        /** @var ProductionBatch $record */
        $record = $this->getRecord();

        return $this->getResource()::getUrl('view', ['record' => $record]);
    }
}
