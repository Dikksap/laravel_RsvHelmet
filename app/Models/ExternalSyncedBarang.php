<?php

namespace App\Models;

use Database\Factories\ExternalSyncedBarangFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kode_barang',
    'external_id',
    'external_variant_id',
    'external_batch_id',
    'item_id',
    'warehouse_id',
    'inventory_movement_id',
    'status',
    'external_occurred_at',
    'synced_at',
])]
class ExternalSyncedBarang extends Model
{
    /** @use HasFactory<ExternalSyncedBarangFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'external_variant_id' => 'integer',
            'external_batch_id' => 'integer',
            'external_occurred_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ExternalItemMapping::class, 'external_variant_id', 'external_variant_id');
    }
}
