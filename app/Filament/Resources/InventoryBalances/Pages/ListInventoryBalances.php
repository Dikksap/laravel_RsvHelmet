<?php

namespace App\Filament\Resources\InventoryBalances\Pages;

use App\Filament\Resources\InventoryBalances\InventoryBalanceResource;
use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use Filament\Actions\CreateAction;

class ListInventoryBalances extends InfiniteScrollListRecords
{
    protected static string $resource = InventoryBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
