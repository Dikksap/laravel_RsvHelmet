<?php

namespace App\Filament\Resources\Uoms\Pages;

use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use App\Filament\Resources\Uoms\UomResource;
use Filament\Actions\CreateAction;

class ListUoms extends InfiniteScrollListRecords
{
    protected static string $resource = UomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
