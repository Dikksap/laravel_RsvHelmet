<?php

namespace App\Models;

use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type',
    'item_id',
    'warehouse_id',
    'quantity',
    'production_batch_id',
    'stock_opname_id',
    'item_request_id',
    'vendor_id',
    'transfer_group_id',
    'parent_movement_id',
    'occurred_at',
    'reference',
    'note',
    'created_by',
])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    public const TYPE_IN_PURCHASE = 'in_purchase';

    public const TYPE_IN_PRODUCTION = 'in_production';

    public const TYPE_OUT_PRODUCTION = 'out_production';

    public const TYPE_OUT_OTHER = 'out_other';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const TYPE_ADJUSTMENT_IN = 'adjustment_in';

    public const TYPE_ADJUSTMENT_OUT = 'adjustment_out';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'occurred_at' => 'datetime',
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

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function itemRequest(): BelongsTo
    {
        return $this->belongsTo(ItemRequest::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function parentMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_movement_id');
    }

    public function childMovements(): HasMany
    {
        return $this->hasMany(self::class, 'parent_movement_id');
    }

    public function externalSyncedBarangs(): HasMany
    {
        return $this->hasMany(ExternalSyncedBarang::class, 'inventory_movement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    public static function formTypeOptions(): array
    {
        return [
            self::TYPE_IN_PURCHASE => 'Masuk — Pembelian',
            self::TYPE_IN_PRODUCTION => 'Masuk — Hasil Produksi',
            self::TYPE_OUT_PRODUCTION => 'Keluar — Produksi',
            self::TYPE_OUT_OTHER => 'Keluar — Lainnya',
            self::TYPE_TRANSFER => 'Pindah Gudang',
            self::TYPE_ADJUSTMENT_IN => 'Penyesuaian (+)',
            self::TYPE_ADJUSTMENT_OUT => 'Penyesuaian (−)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_IN_PURCHASE => 'Masuk — Pembelian',
            self::TYPE_IN_PRODUCTION => 'Masuk — Hasil Produksi',
            self::TYPE_OUT_PRODUCTION => 'Keluar — Produksi',
            self::TYPE_OUT_OTHER => 'Keluar — Lainnya',
            self::TYPE_TRANSFER_IN => 'Pindah Gudang (masuk)',
            self::TYPE_TRANSFER_OUT => 'Pindah Gudang (keluar)',
            self::TYPE_ADJUSTMENT_IN => 'Penyesuaian (+)',
            self::TYPE_ADJUSTMENT_OUT => 'Penyesuaian (−)',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function incomingTypes(): array
    {
        return [
            self::TYPE_IN_PURCHASE,
            self::TYPE_IN_PRODUCTION,
            self::TYPE_TRANSFER_IN,
            self::TYPE_ADJUSTMENT_IN,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function outgoingTypes(): array
    {
        return [
            self::TYPE_OUT_PRODUCTION,
            self::TYPE_OUT_OTHER,
            self::TYPE_TRANSFER_OUT,
            self::TYPE_ADJUSTMENT_OUT,
        ];
    }

    public static function isOutgoingType(string $type): bool
    {
        return in_array($type, self::outgoingTypes(), true);
    }

    public function isIncoming(): bool
    {
        return in_array($this->type, self::incomingTypes(), true);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->type] ?? $this->type;
    }

    public function getSignedQuantityAttribute(): float
    {
        return $this->isIncoming() ? (float) $this->quantity : -1 * (float) $this->quantity;
    }

    public function scopeOutgoing(Builder $query): void
    {
        $query->whereIn('type', self::outgoingTypes());
    }

    public function scopeIncoming(Builder $query): void
    {
        $query->whereIn('type', self::incomingTypes());
    }
}
