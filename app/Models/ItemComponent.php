<?php

namespace App\Models;

use Database\Factories\ItemComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'parent_item_id',
    'component_item_id',
    'quantity',
    'note',
    'is_active',
])]
class ItemComponent extends Model
{
    /** @use HasFactory<ItemComponentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'parent_item_id');
    }

    public function componentItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }

    /**
     * Cegah komposisi melingkar: barang tidak boleh (secara tidak langsung)
     * menjadi komponen dari komponennya sendiri.
     */
    public static function wouldCreateCycle(string $parentItemId, string $componentItemId): bool
    {
        if ($parentItemId === $componentItemId) {
            return true;
        }

        $visited = [];
        $stack = [$componentItemId];

        while ($stack !== []) {
            $current = array_pop($stack);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            $children = self::query()
                ->where('parent_item_id', $current)
                ->where('is_active', true)
                ->pluck('component_item_id')
                ->all();

            foreach ($children as $child) {
                if ($child === $parentItemId) {
                    return true;
                }

                $stack[] = $child;
            }
        }

        return false;
    }
}
