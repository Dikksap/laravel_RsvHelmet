<?php

namespace App\Filament\Resources\ExternalSyncedBarangs\Pages;

use App\Filament\Resources\ExternalSyncedBarangs\ExternalSyncedBarangResource;
use Filament\Resources\Pages\ListRecords;

class ListExternalSyncedBarangs extends ListRecords
{
    protected static string $resource = ExternalSyncedBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
