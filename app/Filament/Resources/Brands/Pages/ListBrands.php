<?php

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use Filament\Actions\CreateAction;

class ListBrands extends InfiniteScrollListRecords
{
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
