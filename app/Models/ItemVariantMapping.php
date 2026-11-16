<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'attribute_id', 'attribute_value_id'])]
class ItemVariantMapping extends Model
{
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ItemAttribute::class, 'attribute_id');
    }

    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(ItemAttributeValue::class, 'attribute_value_id');
    }
}
