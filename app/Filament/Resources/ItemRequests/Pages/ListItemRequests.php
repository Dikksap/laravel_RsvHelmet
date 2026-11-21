<?php

namespace App\Filament\Resources\ItemRequests\Pages;

use App\Filament\Resources\ItemRequests\ItemRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListItemRequests extends ListRecords
{
    protected static string $resource = ItemRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
