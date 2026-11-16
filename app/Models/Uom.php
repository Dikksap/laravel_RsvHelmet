<?php

namespace App\Models;

use Database\Factories\UomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_active'])]
class Uom extends Model
{
    /** @use HasFactory<UomFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'base_uom_id');
    }

    public function weightItems(): HasMany
    {
        return $this->hasMany(Item::class, 'weight_uom_id');
    }

    public function dimensionItems(): HasMany
    {
        return $this->hasMany(Item::class, 'dimension_uom_id');
    }
}
