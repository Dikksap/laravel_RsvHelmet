<?php

namespace App\Filament\Resources\Warehouses\Pages;

use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use App\Filament\Resources\Warehouses\WarehouseResource;
use Filament\Actions\CreateAction;

class ListWarehouses extends InfiniteScrollListRecords
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
