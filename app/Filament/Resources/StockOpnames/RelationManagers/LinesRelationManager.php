<?php

namespace App\Filament\Resources\StockOpnames\RelationManagers;

use App\Filament\Exports\StockOpnameLineExporter;
use App\Filament\Imports\StockOpnameLineImporter;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\StockOpnameLine;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Daftar hitung fisik';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['item', 'opname']))
            ->columns([
                TextColumn::make('item.item_code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('item.item_name')
                    ->label('Barang')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('system_quantity')
                    ->label('Stok sistem')
                    ->numeric(maxDecimalPlaces: 3),
                TextInputColumn::make('counted_quantity')
                    ->label('Stok fisik')
                    ->state(fn (StockOpnameLine $record): ?string => self::formatQuantity($record->counted_quantity))
                    ->type('number')
                    ->inputMode('decimal')
                    ->step('0.001')
                    ->rules(['numeric', 'min:0'])
                    ->disabled(fn (StockOpnameLine $record): bool => ! $record->opname->isDraft()),
                TextColumn::make('difference_quantity')
                    ->label('Selisih')
                    ->state(fn (StockOpnameLine $record): ?float => $record->difference_quantity)
                    ->numeric(maxDecimalPlaces: 3)
                    ->placeholder('—')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state < 0 => 'danger',
                        $state > 0 => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('adjustment_quantity')
                    ->label('Penyesuaian')
                    ->numeric(maxDecimalPlaces: 3)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('counter.name')
                    ->label('Dihitung oleh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('counted_at')
                    ->label('Waktu hitung')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('count_status')
                    ->label('Status hitung')
                    ->options([
                        'done' => 'Sudah dihitung',
                        'pending' => 'Belum dihitung',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'done' => $query->whereNotNull('counted_quantity'),
                        'pending' => $query->whereNull('counted_quantity'),
                        default => $query,
                    }),
            ])
            ->headerActions([
                Action::make('fillSystem')
                    ->label('Isi sesuai sistem')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('gray')
                    ->visible(fn (): bool => $this->getOwnerRecord()->isDraft())
                    ->requiresConfirmation()
                    ->modalHeading('Isi sesuai stok sistem?')
                    ->modalDescription('Semua barang yang belum dihitung akan diisi dengan stok sistem saat opname dibuat.')
                    ->action(function (): void {
                        $this->getOwnerRecord()->lines()
                            ->whereNull('counted_quantity')
                            ->update([
                                'counted_quantity' => DB::raw('system_quantity'),
                                'counted_at' => now(),
                                'counted_by' => auth()->id(),
                                'updated_at' => now(),
                            ]);

                        Notification::make()
                            ->success()
                            ->title('Hitungan diisi sesuai stok sistem')
                            ->send();
                    }),
                Action::make('clearCounts')
                    ->label('Kosongkan hitungan')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn (): bool => $this->getOwnerRecord()->isDraft())
                    ->requiresConfirmation()
                    ->modalHeading('Kosongkan semua hitungan?')
                    ->modalDescription('Seluruh jumlah fisik yang sudah diisi akan dihapus.')
                    ->action(function (): void {
                        $this->getOwnerRecord()->lines()->update([
                            'counted_quantity' => null,
                            'adjustment_quantity' => null,
                            'counted_at' => null,
                            'counted_by' => null,
                            'updated_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Hitungan dikosongkan')
                            ->send();
                    }),
                CreateAction::make()
                    ->label('Tambah barang')
                    ->visible(fn (): bool => $this->getOwnerRecord()->isDraft())
                    ->schema([
                        Select::make('item_id')
                            ->label('Barang')
                            ->options(fn (): array => Item::query()
                                ->where('is_stockable', true)
                                ->where('has_variants', false)
                                ->whereNotIn('id', $this->getOwnerRecord()->lines()->select('item_id'))
                                ->orderBy('item_name')
                                ->get()
                                ->mapWithKeys(fn (Item $item): array => [$item->id => "{$item->item_code} — {$item->item_name}"])
                                ->all())
                            ->searchable()
                            ->required()
                            ->helperText('Barang di luar snapshot awal opname.'),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->rows(2),
                    ])
                    ->mutateDataUsing(function (array $data): array {
                        $data['system_quantity'] = (float) InventoryBalance::query()
                            ->where('warehouse_id', $this->getOwnerRecord()->warehouse_id)
                            ->where('item_id', $data['item_id'])
                            ->value('quantity_on_hand');

                        return $data;
                    })
                    ->successNotificationTitle('Barang ditambahkan'),
                ImportAction::make()
                    ->label('Impor CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->importer(StockOpnameLineImporter::class)
                    ->options(fn (): array => [
                        'stock_opname_id' => $this->getOwnerRecord()->getKey(),
                    ])
                    ->visible(fn (): bool => $this->getOwnerRecord()->isDraft()),
                ExportAction::make()
                    ->label('Ekspor CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->exporter(StockOpnameLineExporter::class)
                    ->formats([ExportFormat::Csv])
                    ->modifyQueryUsing(fn (): Builder => StockOpnameLine::query()
                        ->where('stock_opname_id', $this->getOwnerRecord()->getKey())
                        ->orderBy('id'))
                    ->modalDescription('Seluruh baris hitung fisik pada opname ini akan diekspor tanpa mengikuti filter tabel.'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (): bool => $this->getOwnerRecord()->isDraft()),
            ])
            ->defaultSort('id')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Belum ada barang hitung fisik')
            ->emptyStateDescription('Barang akan dimuat otomatis saat opname dibuat.');
    }

    public static function formatQuantity(mixed $quantity): ?string
    {
        return StockOpnameLine::formatQuantity($quantity);
    }
}
