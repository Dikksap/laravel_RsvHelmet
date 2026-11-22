<?php

namespace App\Filament\Resources\ItemAttributes\Pages;

use App\Filament\Resources\ItemAttributes\ItemAttributeResource;
use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use Filament\Actions\CreateAction;

class ListItemAttributes extends InfiniteScrollListRecords
{
    protected static string $resource = ItemAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
