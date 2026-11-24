<?php

namespace App\Filament\Resources\InventoryBalances\Pages;

use App\Filament\Pages\WarehouseStockDetail;
use App\Filament\Resources\InventoryBalances\InventoryBalanceResource;
use App\Models\Warehouse;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryBalance extends CreateRecord
{
    protected static string $resource = InventoryBalanceResource::class;

    /**
     * Gudang awal yang dipilih dari halaman stok per gudang.
     */
    public ?string $warehouseId = null;

    protected function fillForm(): void
    {
        $warehouseId = request()->query('warehouse_id');

        $this->warehouseId = filled($warehouseId) && Warehouse::query()->whereKey($warehouseId)->exists()
            ? (string) $warehouseId
            : null;

        $this->callHook('beforeFill');

        $this->form->fill(
            filled($this->warehouseId) ? ['warehouse_id' => $this->warehouseId] : [],
        );

        $this->callHook('afterFill');
    }

    protected function getRedirectUrl(): string
    {
        $warehouseId = $this->record?->getAttribute('warehouse_id') ?? $this->warehouseId;

        if (filled($warehouseId)) {
            return WarehouseStockDetail::getUrl(['warehouse' => $warehouseId]);
        }

        return parent::getRedirectUrl();
    }
}
