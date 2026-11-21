<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'warehouse_id',
    'destination_warehouse_id',
])]
class ItemRequestSetting extends Model
{
    /**
     * Ambil baris pengaturan tunggal, buat bila belum ada.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function isConfigured(): bool
    {
        return filled($this->warehouse_id) && filled($this->destination_warehouse_id);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }
}
