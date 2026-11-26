<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use App\Filament\Resources\Vendors\VendorResource;
use Filament\Actions\CreateAction;

class ListVendors extends InfiniteScrollListRecords
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
