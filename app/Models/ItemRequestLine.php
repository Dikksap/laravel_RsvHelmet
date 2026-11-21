<?php

namespace App\Models;

use Database\Factories\ItemRequestLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'item_request_id',
    'item_id',
    'requested_item_name',
    'quantity',
    'note',
])]
class ItemRequestLine extends Model
{
    /** @use HasFactory<ItemRequestLineFactory> */
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

    public function request(): BelongsTo
    {
        return $this->belongsTo(ItemRequest::class, 'item_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getIsMappedAttribute(): bool
    {
        return filled($this->item_id);
    }
}
