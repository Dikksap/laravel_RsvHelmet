<?php

namespace App\Filament\Resources\InventoryMovements\Pages;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use Filament\Actions\CreateAction;

class ListInventoryMovements extends InfiniteScrollListRecords
{
    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Catat transaksi'),
        ];
    }
}
