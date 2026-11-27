<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Models\Delivery;
use App\Services\DeliveryService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

class ViewDelivery extends ViewRecord
{
    protected static string $resource = DeliveryResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi pengiriman')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Nomor'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Delivery::statusOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => DeliveryResource::statusColor($state)),
                        TextEntry::make('warehouse.name')
                            ->label('Gudang asal'),
                        TextEntry::make('recipient_name')
                            ->label('Penerima'),
                        TextEntry::make('delivery_date')
                            ->label('Tanggal kirim')
                            ->date(),
                        TextEntry::make('creator.name')
                            ->label('Dibuat oleh')
                            ->placeholder('—'),
                        TextEntry::make('shipped_at')
                            ->label('Dikirim pada')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('shipper.name')
                            ->label('Dikirim oleh')
                            ->placeholder('—'),
                        TextEntry::make('cancelled_at')
                            ->label('Dibatalkan pada')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('note')
                            ->label('Catatan')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editNote')
                ->label(fn (Delivery $record): string => filled($record->note) ? 'Ubah catatan' : 'Tambah catatan')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn (Delivery $record): bool => ! $record->isCancelled())
                ->fillForm(fn (Delivery $record): array => ['note' => $record->note])
                ->schema([
                    Textarea::make('note')
                        ->label('Catatan pengiriman')
                        ->rows(4)
                        ->maxLength(1000)
                        ->helperText('Catatan ini ikut tercetak pada surat jalan.'),
                ])
                ->modalHeading('Catatan pengiriman')
                ->modalSubmitActionLabel('Simpan catatan')
                ->action(function (Delivery $record, array $data): void {
                    $record->update(['note' => filled($data['note'] ?? null) ? $data['note'] : null]);
                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Catatan pengiriman disimpan')
                        ->send();
                }),
            Action::make('printDeliveryNote')
                ->label('Cetak surat jalan')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (Delivery $record): bool => $record->isShipped())
                ->url(
                    fn (Delivery $record): string => route('filament.admin.deliveries.pdf', ['delivery' => $record]),
                    shouldOpenInNewTab: true,
                ),
            Action::make('ship')
                ->label('Kirim barang')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (Delivery $record): bool => $record->isDraft())
                ->requiresConfirmation()
                ->modalHeading('Kirim barang sekarang?')
                ->modalDescription(fn (Delivery $record): string => 'Stok barang pada pengiriman ini akan dikurangi dari '
                    .($record->warehouse?->name ?? 'gudang asal')
                    .'. Setelah dikirim, dokumen tidak bisa diubah lagi.')
                ->modalSubmitActionLabel('Kirim')
                ->action(function (Delivery $record): void {
                    try {
                        app(DeliveryService::class)->ship($record);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Pengiriman gagal dikirim')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->persistent()
                            ->send();

                        throw new Halt;
                    }

                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Barang sudah dikirim')
                        ->body('Transaksi stok keluar sudah dicatat pada gudang asal.')
                        ->send();
                }),
            Action::make('cancelDelivery')
                ->label('Batalkan')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Delivery $record): bool => $record->isDraft() || $record->isShipped())
                ->requiresConfirmation()
                ->modalHeading('Batalkan pengiriman?')
                ->modalDescription(fn (Delivery $record): string => $record->isShipped()
                    ? 'Pengiriman sudah dikirim. Stok barang akan dikembalikan ke gudang asal dan dokumen ditandai dibatalkan.'
                    : 'Dokumen akan ditandai dibatalkan tanpa mengubah stok gudang.')
                ->modalSubmitActionLabel('Batalkan pengiriman')
                ->action(function (Delivery $record): void {
                    $wasShipped = $record->isShipped();

                    try {
                        app(DeliveryService::class)->cancel($record);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Pengiriman gagal dibatalkan')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->persistent()
                            ->send();

                        throw new Halt;
                    }

                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Pengiriman dibatalkan')
                        ->body($wasShipped
                            ? 'Stok barang sudah dikembalikan ke gudang asal.'
                            : 'Dokumen ditandai dibatalkan tanpa mengubah stok gudang.')
                        ->send();
                }),
            DeleteAction::make()
                ->visible(fn (Delivery $record): bool => $record->isDraft())
                ->successRedirectUrl(DeliveryResource::getUrl('index')),
        ];
    }
}
