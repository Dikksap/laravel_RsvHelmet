<?php

namespace App\Models;

use Database\Factories\ProductionBatchMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'production_batch_id',
    'item_id',
    'quantity_required',
])]
class ProductionBatchMaterial extends Model
{
    /** @use HasFactory<ProductionBatchMaterialFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:3',
        ];
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getActualQuantityAttribute(): float
    {
        if (! $this->production_batch_id || ! $this->item_id) {
            return 0.0;
        }

        return (float) InventoryMovement::query()
            ->where('production_batch_id', $this->production_batch_id)
            ->where('item_id', $this->item_id)
            ->where('type', InventoryMovement::TYPE_OUT_PRODUCTION)
            ->sum('quantity');
    }

    public function getStockOnHandAttribute(): float
    {
        if (! $this->item) {
            return 0.0;
        }

        $warehouseId = $this->productionBatch?->material_warehouse_id;

        $balances = $this->item->inventoryBalances()
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->get();

        return (float) $balances->sum(fn (InventoryBalance $balance): float => (float) $balance->quantity_on_hand);
    }

    public function getShortageQuantityAttribute(): float
    {
        return max((float) $this->quantity_required - $this->stock_on_hand, 0.0);
    }
}
