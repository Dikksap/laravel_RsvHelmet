<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Filament\Resources\Pages\InfiniteScrollListRecords;
use Filament\Actions\CreateAction;

class ListDeliveries extends InfiniteScrollListRecords
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat pengiriman'),
        ];
    }
}
