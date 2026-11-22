<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use Filament\Actions\CreateAction;

class ListStockOpnames extends InfiniteScrollListRecords
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat stok opname'),
        ];
    }
}
