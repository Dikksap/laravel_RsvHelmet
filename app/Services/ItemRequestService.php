<?php

namespace App\Services;

use App\Models\ItemRequest;
use App\Models\ItemRequestLine;
use App\Models\ItemRequestSetting;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemRequestService
{
    /**
     * Setujui atau tolak barang permintaan satu per satu, lalu pindahkan stok
     * barang yang disetujui antar gudang.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function approve(ItemRequest $itemRequest, array $attributes): ItemRequest
    {
        return DB::transaction(function () use ($itemRequest, $attributes): ItemRequest {
            /** @var ItemRequest $locked */
            $locked = ItemRequest::query()->lockForUpdate()->findOrFail($itemRequest->getKey());

            $this->ensurePending($locked);

            $settings = ItemRequestSetting::current();

            $originId = $attributes['warehouse_id'] ?? $settings->warehouse_id;
            $destinationId = $attributes['destination_warehouse_id'] ?? $settings->destination_warehouse_id;

            if (blank($originId) || blank($destinationId)) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Gudang asal dan gudang tujuan belum diatur. Atur terlebih dahulu di menu Pengaturan Permintaan.',
                ]);
            }

            if ((int) $originId === (int) $destinationId) {
                throw ValidationException::withMessages([
                    'destination_warehouse_id' => 'Gudang tujuan harus berbeda dari gudang asal.',
                ]);
            }

            $activeWarehouseCount = Warehouse::query()
                ->where('is_active', true)
                ->whereIn('id', [$originId, $destinationId])
                ->count();

            if ($activeWarehouseCount !== 2) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Gudang asal dan gudang tujuan harus aktif.',
                ]);
            }

            /** @var Collection<int, ItemRequestLine> $lines */
            $lines = $locked->lines()
                ->with('item')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'Permintaan ini tidak memiliki barang untuk ditransfer.',
                ]);
            }

            $decisions = collect($attributes['lines'] ?? [])
                ->filter(fn (mixed $row): bool => is_array($row))
                ->mapWithKeys(fn (array $row): array => [(int) ($row['line_id'] ?? 0) => $row]);

            if ($decisions->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'Tentukan barang mana yang disetujui atau ditolak.',
                ]);
            }

            $approvedItems = [];
            $rejectedNotes = [];

            foreach ($lines as $line) {
                $decision = $decisions->get((int) $line->getKey());

                if ($decision === null) {
                    throw ValidationException::withMessages([
                        'lines' => 'Keputusan untuk semua barang wajib diisi.',
                    ]);
                }

                if (! (bool) ($decision['approved'] ?? false)) {
                    $note = trim((string) ($decision['rejection_note'] ?? ''));

                    if ($note === '') {
                        throw ValidationException::withMessages([
                            'lines' => 'Alasan penolakan untuk '.$this->lineLabel($line).' wajib diisi.',
                        ]);
                    }

                    if (mb_strlen($note) > 255) {
                        throw ValidationException::withMessages([
                            'lines' => 'Alasan penolakan untuk '.$this->lineLabel($line).' maksimal 255 karakter.',
                        ]);
                    }

                    $rejectedNotes[] = $note;

                    continue;
                }

                if (blank($line->item_id)) {
                    throw ValidationException::withMessages([
                        'lines' => 'Barang '.$this->lineLabel($line).' belum dipetakan sehingga tidak bisa disetujui.',
                    ]);
                }

                $approvedQuantity = (float) ($decision['quantity'] ?? 0);

                if ($approvedQuantity <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => 'Jumlah yang disetujui untuk '.$this->lineLabel($line).' harus lebih dari 0.',
                    ]);
                }

                if ($approvedQuantity > (float) $line->quantity) {
                    throw ValidationException::withMessages([
                        'lines' => 'Jumlah yang disetujui untuk '.$this->lineLabel($line).' melebihi jumlah yang diminta.',
                    ]);
                }

                $approvedItems[] = [
                    'item_id' => $line->item_id,
                    'quantity' => $approvedQuantity,
                ];
            }

            if ($approvedItems !== []) {
                app(StockMovementService::class)->transferMany([
                    'warehouse_id' => $originId,
                    'destination_warehouse_id' => $destinationId,
                    'occurred_at' => $attributes['occurred_at'] ?? now(),
                    'reference' => $locked->code,
                    'note' => $attributes['note'] ?? null,
                    'item_request_id' => $locked->getKey(),
                    'created_by' => auth()->id(),
                    'items' => $approvedItems,
                ]);
            }

            foreach ($lines as $line) {
                /** @var array<string, mixed> $decision */
                $decision = $decisions->get((int) $line->getKey());
                $isApproved = (bool) ($decision['approved'] ?? false);

                $line->update([
                    'approval_status' => $isApproved
                        ? ItemRequestLine::APPROVAL_APPROVED
                        : ItemRequestLine::APPROVAL_REJECTED,
                    'approved_quantity' => $isApproved ? (float) $decision['quantity'] : null,
                    'rejection_note' => $isApproved
                        ? null
                        : trim((string) ($decision['rejection_note'] ?? '')),
                ]);
            }

            $status = $this->resolveStatus($lines);

            $isRejected = $status === ItemRequest::STATUS_REJECTED;

            $locked->update([
                'status' => $status,
                'warehouse_id' => $originId,
                'destination_warehouse_id' => $destinationId,
                'approved_by' => $isRejected ? null : auth()->id(),
                'approved_at' => $isRejected ? null : now(),
                'rejected_by' => $isRejected ? auth()->id() : null,
                'rejected_at' => $isRejected ? now() : null,
                'rejection_note' => $isRejected
                    ? (implode('; ', array_unique($rejectedNotes)) ?: 'Seluruh barang permintaan ditolak.')
                    : null,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Tolak seluruh permintaan tanpa mengubah stok gudang.
     */
    public function reject(ItemRequest $itemRequest, string $reason): ItemRequest
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_note' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($itemRequest, $reason): ItemRequest {
            /** @var ItemRequest $locked */
            $locked = ItemRequest::query()->lockForUpdate()->findOrFail($itemRequest->getKey());

            $this->ensurePending($locked);

            $locked->update([
                'status' => ItemRequest::STATUS_REJECTED,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_note' => $reason,
            ]);

            $locked->lines()->update([
                'approval_status' => ItemRequestLine::APPROVAL_REJECTED,
                'approved_quantity' => null,
                'rejection_note' => Str::limit($reason, 255, ''),
            ]);

            return $locked->refresh();
        });
    }

    /**
     * @param  Collection<int, ItemRequestLine>  $lines
     */
    private function resolveStatus(Collection $lines): string
    {
        if ($lines->every(fn (ItemRequestLine $line): bool => $line->isRejected())) {
            return ItemRequest::STATUS_REJECTED;
        }

        $isComplete = $lines->every(
            fn (ItemRequestLine $line): bool => $line->isApproved()
                && (float) $line->approved_quantity >= (float) $line->quantity,
        );

        return $isComplete ? ItemRequest::STATUS_COMPLETED : ItemRequest::STATUS_PARTIAL;
    }

    private function lineLabel(ItemRequestLine $line): string
    {
        return $line->item?->item_code
            ?? $line->item?->item_name
            ?? $line->requested_item_name
            ?? 'baris #'.$line->getKey();
    }

    private function ensurePending(ItemRequest $itemRequest): void
    {
        if (! $itemRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Hanya permintaan berstatus baru yang bisa diproses.',
            ]);
        }
    }
}
