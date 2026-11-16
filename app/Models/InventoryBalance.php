<?php

namespace App\Models;

use Database\Factories\InventoryBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'warehouse_id',
    'item_id',
    'quantity_on_hand',
    'minimum_quantity',
    'last_counted_at',
])]
class InventoryBalance extends Model
{
    /** @use HasFactory<InventoryBalanceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'minimum_quantity' => 'decimal:3',
            'last_counted_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    protected function stockStatus(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): string {
                $onHand = (float) $attributes['quantity_on_hand'];

                if ($onHand <= 0) {
                    return 'out';
                }

                if ($onHand <= (float) $attributes['minimum_quantity']) {
                    return 'low';
                }

                return 'ok';
            },
        );
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->whereColumn('quantity_on_hand', '<=', 'minimum_quantity');
    }
}
