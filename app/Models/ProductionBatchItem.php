<?php

namespace App\Models;

use Database\Factories\ProductionBatchItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'production_batch_id',
    'item_id',
    'quantity_planned',
])]
class ProductionBatchItem extends Model
{
    /** @use HasFactory<ProductionBatchItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_planned' => 'decimal:3',
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

        $movementQty = (float) InventoryMovement::query()
            ->where('production_batch_id', $this->production_batch_id)
            ->where('item_id', $this->item_id)
            ->where('type', InventoryMovement::TYPE_IN_PRODUCTION)
            ->sum('quantity');

        if ($movementQty > 0) {
            return $movementQty;
        }

        $outputWarehouseId = $this->productionBatch?->output_warehouse_id;

        if ($outputWarehouseId) {
            $stockInOutput = (float) InventoryBalance::query()
                ->where('warehouse_id', $outputWarehouseId)
                ->where('item_id', $this->item_id)
                ->value('quantity_on_hand');

            if ($stockInOutput > 0) {
                return $stockInOutput;
            }
        }

        return (float) InventoryBalance::query()
            ->where('item_id', $this->item_id)
            ->sum('quantity_on_hand');
    }
}
