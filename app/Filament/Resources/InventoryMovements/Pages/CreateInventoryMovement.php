<?php

namespace App\Filament\Resources\InventoryMovements\Pages;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use App\Services\ExternalBarangSyncService;
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
                && (bool) ($data['source_external'] ?? false)
            ) {
                if (filled($data['external_batch_code'] ?? null)) {
                    return $this->createBatchOutputFromExternalApi($data);
                }

                return $this->createProductionOutputFromExternalApi($data);
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

    private function createBatchOutputFromExternalApi(array $data): InventoryMovement
    {
        $warehouse = Warehouse::query()->find($data['warehouse_id'] ?? null);

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang hasil produksi wajib dipilih.',
            ]);
        }

        $result = app(ExternalBarangSyncService::class)->syncBatchOutput(
            $warehouse,
            (string) $data['external_batch_code'],
            [
                'production_batch_id' => $data['production_batch_id'] ?? null,
                'tanggal_awal' => $data['tanggal_awal'] ?? null,
                'tanggal_akhir' => $data['tanggal_akhir'] ?? null,
                'status' => $data['external_status'],
                'occurred_at' => $data['occurred_at'] ?? now(),
                'quantities' => collect($data['external_quantities'] ?? [])
                    ->mapWithKeys(fn (array $row): array => [
                        (int) ($row['external_variant_id'] ?? 0) => (int) ($row['quantity'] ?? 0),
                    ])
                    ->all(),
            ],
        );

        return $result['movement'];
    }

    private function createProductionOutputFromExternalApi(array $data): InventoryMovement
    {
        $warehouse = Warehouse::query()->find($data['warehouse_id'] ?? null);

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang hasil produksi wajib dipilih.',
            ]);
        }

        $result = app(ExternalBarangSyncService::class)->syncVariantOutput(
            $warehouse,
            (int) ($data['external_variant_id'] ?? 0),
            [
                'production_batch_id' => $data['production_batch_id'] ?? null,
                'tanggal_awal' => $data['tanggal_awal'] ?? null,
                'tanggal_akhir' => $data['tanggal_akhir'] ?? null,
                'status' => $data['external_status'],
                'occurred_at' => $data['occurred_at'] ?? now(),
                'quantity' => $data['external_quantity'] ?? null,
            ],
        );

        return $result['movement'];
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
