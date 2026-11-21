<?php

namespace App\Models;

use Database\Factories\StockOpnameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'warehouse_id',
    'status',
    'note',
    'created_by',
    'completed_at',
    'completed_by',
])]
class StockOpname extends Model
{
    /** @use HasFactory<StockOpnameFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockOpnameLine::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function countedLinesCount(): int
    {
        return $this->lines()->whereNotNull('counted_quantity')->count();
    }

    public function differenceLinesCount(): int
    {
        return $this->lines()
            ->whereNotNull('counted_quantity')
            ->whereColumn('counted_quantity', '!=', 'system_quantity')
            ->count();
    }
}
