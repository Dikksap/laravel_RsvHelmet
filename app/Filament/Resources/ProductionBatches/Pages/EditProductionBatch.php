<?php

namespace App\Filament\Resources\ProductionBatches\Pages;

use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Services\BatchRequirementService;
use Filament\Resources\Pages\EditRecord;

class EditProductionBatch extends EditRecord
{
    protected static string $resource = ProductionBatchResource::class;

    protected function afterSave(): void
    {
        app(BatchRequirementService::class)->recalculate($this->getRecord());
    }
}
