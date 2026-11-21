<?php

namespace App\Models;

use Database\Factories\ItemRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'requester_name',
    'division_id',
    'note',
    'status',
    'warehouse_id',
    'destination_warehouse_id',
    'approved_by',
    'approved_at',
    'rejected_by',
    'rejected_at',
    'rejection_note',
])]
class ItemRequest extends Model
{
    /** @use HasFactory<ItemRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (ItemRequest $itemRequest): void {
            $itemRequest->forceFill([
                'code' => 'PR-'.$itemRequest->created_at->format('Ymd').'-'.str_pad((string) $itemRequest->getKey(), 4, '0', STR_PAD_LEFT),
            ])->saveQuietly();
        });
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Baru',
            self::STATUS_PROCESSING => 'Diproses',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_REJECTED => 'Ditolak',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ItemRequestLine::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }
}
