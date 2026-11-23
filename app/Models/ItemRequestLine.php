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
    'approval_status',
    'approved_quantity',
    'rejection_note',
    'note',
])]
class ItemRequestLine extends Model
{
    /** @use HasFactory<ItemRequestLineFactory> */
    use HasFactory;

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'approved_quantity' => 'decimal:3',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function approvalStatusOptions(): array
    {
        return [
            self::APPROVAL_PENDING => 'Menunggu',
            self::APPROVAL_APPROVED => 'Disetujui',
            self::APPROVAL_REJECTED => 'Ditolak',
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

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === self::APPROVAL_REJECTED;
    }
}
