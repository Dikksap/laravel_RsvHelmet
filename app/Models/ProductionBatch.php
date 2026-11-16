<?php

namespace App\Models;

use Database\Factories\ProductionBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'batch_code',
    'status',
    'material_warehouse_id',
    'output_warehouse_id',
    'started_at',
    'finished_at',
    'notes',
    'created_by',
])]
class ProductionBatch extends Model
{
    /** @use HasFactory<ProductionBatchFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'finished_at' => 'date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Rencana',
            self::STATUS_RUNNING => 'Berjalan',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionBatchItem::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProductionBatchMaterial::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function materialWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'material_warehouse_id');
    }

    public function outputWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'output_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function getTotalPlannedQuantityAttribute(): float
    {
        return (float) $this->items->sum('quantity_planned');
    }
}
