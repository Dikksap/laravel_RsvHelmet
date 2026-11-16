<?php

namespace App\Filament\Resources\InventoryMovements\Pages;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\InventoryMovement;
use App\Services\StockMovementService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateInventoryMovement extends CreateRecord
{
    protected static string $resource = InventoryMovementResource::class;

    protected static ?string $title = 'Catat Transaksi Stok';

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(StockMovementService::class);

        try {
            if (($data['type'] ?? null) === InventoryMovement::TYPE_TRANSFER) {
                [$movement] = $service->transfer($data);

                return $movement;
            }

            if (
                ($data['type'] ?? null) === InventoryMovement::TYPE_IN_PRODUCTION
                && (bool) ($data['consume_components'] ?? true)
                && filled($data['production_batch_id'] ?? null)
            ) {
                return $service->recordProductionOutput($data);
            }

            return $service->record($data);
        } catch (ValidationException $exception) {
            throw $this->prefixFormErrorKeys($exception);
        }
    }

    private function prefixFormErrorKeys(ValidationException $exception): ValidationException
    {
        $prefix = $this->form->getStatePath();

        if (blank($prefix)) {
            return $exception;
        }

        $errors = collect($exception->errors())
            ->mapWithKeys(fn (array $messages, string $key): array => [
                str_starts_with($key, "{$prefix}.") ? $key : "{$prefix}.{$key}" => $messages,
            ])
            ->all();

        return ValidationException::withMessages($errors);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
