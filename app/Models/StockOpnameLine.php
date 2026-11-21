<?php

namespace App\Models;

use Database\Factories\StockOpnameLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_opname_id',
    'item_id',
    'system_quantity',
    'counted_quantity',
    'adjustment_quantity',
    'counted_at',
    'counted_by',
    'note',
])]
class StockOpnameLine extends Model
{
    /** @use HasFactory<StockOpnameLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'adjustment_quantity' => 'decimal:3',
            'counted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (StockOpnameLine $line): void {
            if (! $line->isDirty('counted_quantity')) {
                return;
            }

            if (filled($line->counted_quantity)) {
                $line->counted_at = now();
                $line->counted_by = auth()->id();

                return;
            }

            $line->counted_at = null;
            $line->counted_by = null;
            $line->adjustment_quantity = null;
        });
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function getDifferenceQuantityAttribute(): ?float
    {
        if ($this->counted_quantity === null) {
            return null;
        }

        return round((float) $this->counted_quantity - (float) $this->system_quantity, 3);
    }

    public static function formatQuantity(mixed $quantity): ?string
    {
        if ($quantity === null) {
            return null;
        }

        $value = rtrim(rtrim((string) $quantity, '0'), '.');

        return $value === '' ? '0' : $value;
    }
}
