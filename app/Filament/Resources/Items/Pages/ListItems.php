<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Exports\ItemExporter;
use App\Filament\Imports\ItemImporter;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;

class ListItems extends InfiniteScrollListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(ItemImporter::class)
                ->label('Impor barang'),
            ExportAction::make()
                ->exporter(ItemExporter::class)
                ->label('Ekspor barang'),
        ];
    }
}
