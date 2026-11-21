<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\StockOpname;
use App\Models\Warehouse;
use App\Services\StockOpnameService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStockOpname extends CreateRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected static ?string $title = 'Buat Stok Opname';

    protected function handleRecordCreation(array $data): Model
    {
        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);

        return app(StockOpnameService::class)->create($warehouse, [
            'note' => $data['note'] ?? null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        /** @var StockOpname $record */
        $record = $this->getRecord();

        return $this->getResource()::getUrl('view', ['record' => $record]);
    }
}
