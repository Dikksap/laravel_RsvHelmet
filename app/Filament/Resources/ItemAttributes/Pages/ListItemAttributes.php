<?php

namespace App\Filament\Resources\ItemAttributes\Pages;

use App\Filament\Resources\ItemAttributes\ItemAttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemAttributes extends ListRecords
{
    protected static string $resource = ItemAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
