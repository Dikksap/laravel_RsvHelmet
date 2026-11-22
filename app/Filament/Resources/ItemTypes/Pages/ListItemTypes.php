<?php

namespace App\Filament\Resources\ItemTypes\Pages;

use App\Filament\Resources\ItemTypes\ItemTypeResource;
use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use Filament\Actions\CreateAction;

class ListItemTypes extends InfiniteScrollListRecords
{
    protected static string $resource = ItemTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
