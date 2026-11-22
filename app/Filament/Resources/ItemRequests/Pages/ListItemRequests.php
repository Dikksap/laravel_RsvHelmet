<?php

namespace App\Filament\Resources\ItemRequests\Pages;

use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Filament\Resources\Pages\InfiniteScrollListRecords;

class ListItemRequests extends InfiniteScrollListRecords
{
    protected static string $resource = ItemRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
