<?php

namespace App\Models;

use Database\Factories\DeliveryLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

#[Fillable([
    'delivery_id',
    'item_id',
    'quantity',
    'note',
])]
class DeliveryLine extends Model
{
    /** @use HasFactory<DeliveryLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function formattedQuantity(): string
    {
        return Number::format((float) $this->quantity, maxPrecision: 3, locale: app()->getLocale());
    }
}
