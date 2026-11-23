<?php

namespace App\Models;

use Database\Factories\ExternalItemMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'item_id',
    'external_variant_id',
    'external_variant_code',
    'external_product_name',
    'external_style_name',
    'external_color_name',
    'external_size_name',
    'is_active',
])]
class ExternalItemMapping extends Model
{
    /** @use HasFactory<ExternalItemMappingFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_variant_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function syncedBarangs(): HasMany
    {
        return $this->hasMany(ExternalSyncedBarang::class, 'external_variant_id', 'external_variant_id');
    }
}
