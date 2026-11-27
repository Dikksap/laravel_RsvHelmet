<?php

namespace App\Filament\Resources\Deliveries;

use App\Filament\Resources\Deliveries\Pages\CreateDelivery;
use App\Filament\Resources\Deliveries\Pages\ListDeliveries;
use App\Filament\Resources\Deliveries\Pages\ViewDelivery;
use App\Filament\Resources\Deliveries\RelationManagers\LinesRelationManager;
use App\Filament\Resources\Deliveries\RelationManagers\MovementsRelationManager;
use App\Models\Delivery;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DeliveryResource extends Resource
{
    protected static ?string $model = Delivery::class;

    protected static string|UnitEnum|null $navigationGroup = 'Pengiriman';

    protected static ?string $navigationLabel = 'Daftar Pengiriman';

    protected static ?string $modelLabel = 'Pengiriman';

    protected static ?string $pluralModelLabel = 'Pengiriman';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationBadge(): ?string
    {
        $count = Delivery::query()
            ->where('status', Delivery::STATUS_DRAFT)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah pengiriman draft yang belum dikirim.';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi pengiriman')
                    ->columns(2)
                    ->schema([
                        Select::make('warehouse_id')
                            ->label('Gudang asal')
                            ->relationship(
                                name: 'warehouse',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true),
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Barang yang dikirim akan mengurangi stok gudang ini.'),
                        TextInput::make('recipient_name')
                            ->label('Penerima')
                            ->required()
                            ->maxLength(150)
                            ->helperText('Nama penerima atau tujuan pengiriman.'),
                        DatePicker::make('delivery_date')
                            ->label('Tanggal kirim')
                            ->default(now())
                            ->required(),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('lines'))
            ->columns([
                TextColumn::make('code')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable()
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('recipient_name')
                    ->label('Penerima')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang asal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lines_count')
                    ->label('Jumlah barang')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Delivery::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('delivery_date')
                    ->label('Tanggal kirim')
                    ->date()
                    ->sortable(),
                TextColumn::make('shipped_at')
                    ->label('Dikirim pada')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label('Dibuat oleh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Delivery::statusOptions()),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang asal')
                    ->relationship('warehouse', 'name'),
                Filter::make('delivery_date')
                    ->label('Tanggal kirim')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari tanggal'),
                        DatePicker::make('until')
                            ->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('delivery_date', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('delivery_date', '<=', $date),
                        )),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton(),
                Action::make('printDeliveryNote')
                    ->label('Cetak surat jalan')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Cetak surat jalan')
                    ->visible(fn (Delivery $record): bool => $record->isShipped())
                    ->url(
                        fn (Delivery $record): string => route('filament.admin.deliveries.pdf', ['delivery' => $record]),
                        shouldOpenInNewTab: true,
                    ),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (Delivery $record): bool => $record->isDraft())
                    ->modalDescription('Dokumen draft beserta seluruh baris barangnya akan dihapus.'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada pengiriman')
            ->emptyStateDescription('Buat pengiriman untuk mencatat barang keluar dari gudang.')
            ->infinite(perPage: 50);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            Delivery::STATUS_SHIPPED => 'success',
            Delivery::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
            MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveries::route('/'),
            'create' => CreateDelivery::route('/create'),
            'view' => ViewDelivery::route('/{record}'),
        ];
    }
}
