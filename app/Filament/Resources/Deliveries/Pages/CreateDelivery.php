<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Models\Delivery;
use App\Services\DeliveryService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDelivery extends CreateRecord
{
    protected static string $resource = DeliveryResource::class;

    protected static ?string $title = 'Buat Pengiriman';

    protected function handleRecordCreation(array $data): Model
    {
        return app(DeliveryService::class)->create([
            'warehouse_id' => $data['warehouse_id'],
            'recipient_name' => $data['recipient_name'],
            'delivery_date' => $data['delivery_date'] ?? null,
            'note' => $data['note'] ?? null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        /** @var Delivery $record */
        $record = $this->getRecord();

        return $this->getResource()::getUrl('view', ['record' => $record]);
    }
}
