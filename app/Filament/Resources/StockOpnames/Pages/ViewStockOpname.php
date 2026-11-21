<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\StockOpname;
use App\Services\StockOpnameService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

class ViewStockOpname extends ViewRecord
{
    protected static string $resource = StockOpnameResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi opname')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Kode'),
                        TextEntry::make('warehouse.name')
                            ->label('Gudang'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => StockOpname::statusOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => StockOpnameResource::statusColor($state)),
                        TextEntry::make('progress')
                            ->label('Progres hitung')
                            ->state(fn (StockOpname $record): string => $record->countedLinesCount()
                                .' / '.$record->lines()->count().' barang'),
                        TextEntry::make('differences')
                            ->label('Barang berselisih')
                            ->badge()
                            ->state(fn (StockOpname $record): int => $record->differenceLinesCount())
                            ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success'),
                        TextEntry::make('creator.name')
                            ->label('Dibuat oleh')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime(),
                        TextEntry::make('completed_at')
                            ->label('Diselesaikan')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('completer.name')
                            ->label('Diselesaikan oleh')
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
            Action::make('post')
                ->label('Posting opname')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (StockOpname $record): bool => $record->isDraft())
                ->requiresConfirmation()
                ->modalHeading('Posting stok opname?')
                ->modalDescription(fn (StockOpname $record): string => $record->countedLinesCount()
                    .' dari '.$record->lines()->count()
                    .' barang sudah dihitung. Baris yang belum dihitung akan dilewati, sedangkan stok sistem disesuaikan dengan stok fisik.')
                ->modalSubmitActionLabel('Posting')
                ->action(function (StockOpname $record): void {
                    try {
                        app(StockOpnameService::class)->post($record);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Stok opname gagal diposting')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->persistent()
                            ->send();

                        throw new Halt;
                    }

                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Stok opname selesai')
                        ->body('Stok sistem sudah disesuaikan dengan hasil hitung fisik.')
                        ->send();
                }),
            Action::make('cancelOpname')
                ->label('Batalkan')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (StockOpname $record): bool => $record->isDraft())
                ->requiresConfirmation()
                ->modalHeading('Batalkan stok opname?')
                ->modalDescription('Dokumen akan ditandai dibatalkan dan tidak bisa diposting lagi.')
                ->modalSubmitActionLabel('Batalkan opname')
                ->action(function (StockOpname $record): void {
                    try {
                        app(StockOpnameService::class)->cancel($record);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Stok opname gagal dibatalkan')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->persistent()
                            ->send();

                        throw new Halt;
                    }

                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Stok opname dibatalkan')
                        ->send();
                }),
            DeleteAction::make()
                ->visible(fn (StockOpname $record): bool => $record->isDraft())
                ->successRedirectUrl(StockOpnameResource::getUrl('index')),
        ];
    }
}
