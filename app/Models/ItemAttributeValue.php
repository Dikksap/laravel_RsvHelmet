<?php

namespace App\Models;

use Database\Factories\ItemAttributeValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['attribute_id', 'value', 'code'])]
class ItemAttributeValue extends Model
{
    /** @use HasFactory<ItemAttributeValueFactory> */
    use HasFactory;

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ItemAttribute::class, 'attribute_id');
    }

    public function variantMappings(): HasMany
    {
        return $this->hasMany(ItemVariantMapping::class, 'attribute_value_id');
    }
}
