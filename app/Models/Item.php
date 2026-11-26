<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'parent_item_id',
    'has_variants',
    'item_code',
    'barcode',
    'item_name',
    'description',
    'image_path',
    'item_type_id',
    'category_id',
    'brand_id',
    'division_id',
    'base_uom_id',
    'weight',
    'weight_uom_id',
    'length',
    'width',
    'height',
    'dimension_uom_id',
    'base_price',
    'standard_cost',
    'is_stockable',
    'is_sellable',
    'is_purchasable',
    'is_active',
])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_variants' => 'boolean',
            'weight' => 'decimal:3',
            'length' => 'decimal:3',
            'width' => 'decimal:3',
            'height' => 'decimal:3',
            'base_price' => 'decimal:2',
            'standard_cost' => 'decimal:2',
            'is_stockable' => 'boolean',
            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    public function weightUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'weight_uom_id');
    }

    public function dimensionUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'dimension_uom_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }

    public function variantMappings(): HasMany
    {
        return $this->hasMany(ItemVariantMapping::class);
    }

    public function externalMapping(): HasOne
    {
        return $this->hasOne(ExternalItemMapping::class);
    }

    public function externalSyncedUnits(): HasMany
    {
        return $this->hasMany(ExternalSyncedBarang::class);
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(ItemComponent::class, 'parent_item_id');
    }

    public function usedInComponents(): HasMany
    {
        return $this->hasMany(ItemComponent::class, 'component_item_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemAttributeValue::class,
            'item_variant_mappings',
            'item_id',
            'attribute_value_id',
        )
            ->withPivot('attribute_id')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeParents(Builder $query): void
    {
        $query->whereNull('parent_item_id');
    }

    public function scopeVariants(Builder $query): void
    {
        $query->whereNotNull('parent_item_id');
    }
}
