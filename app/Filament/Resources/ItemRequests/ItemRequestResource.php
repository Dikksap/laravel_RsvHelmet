<?php

namespace App\Filament\Resources\ItemRequests;

use App\Filament\Resources\ItemRequests\Pages\EditItemRequest;
use App\Filament\Resources\ItemRequests\Pages\ListItemRequests;
use App\Filament\Resources\ItemRequests\RelationManagers\LinesRelationManager;
use App\Models\InventoryBalance;
use App\Models\ItemRequest;
use App\Models\ItemRequestLine;
use App\Models\ItemRequestSetting;
use App\Services\ItemRequestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use UnitEnum;

class ItemRequestResource extends Resource
{
    protected static ?string $model = ItemRequest::class;

    protected static string|UnitEnum|null $navigationGroup = 'Permintaan Barang';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Daftar Permintaan';

    protected static ?string $modelLabel = 'Permintaan Barang';

    protected static ?string $pluralModelLabel = 'Permintaan Barang';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'code';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ItemRequest::query()
            ->where('status', ItemRequest::STATUS_PENDING)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah permintaan barang baru yang menunggu persetujuan.';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pemohon')
                    ->columns(2)
                    ->schema([
                        TextInput::make('requester_name')
                            ->label('Nama pemohon')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('division_id')
                            ->label('Divisi')
                            ->relationship('division', 'name')
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
                Section::make('Status & persetujuan')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(ItemRequest::statusOptions())
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('approved_by')
                            ->label('Disetujui oleh')
                            ->relationship('approver', 'name')
                            ->placeholder('—')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('warehouse_id')
                            ->label('Gudang asal')
                            ->relationship('warehouse', 'name')
                            ->placeholder('—')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('destination_warehouse_id')
                            ->label('Gudang tujuan')
                            ->relationship('destinationWarehouse', 'name')
                            ->placeholder('—')
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('approved_at')
                            ->label('Disetujui pada')
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('rejected_at')
                            ->label('Ditolak pada')
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('rejection_note')
                            ->label('Alasan penolakan')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('requester_name')
                    ->label('Pemohon')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('division.name')
                    ->label('Divisi')
                    ->sortable(),
                TextColumn::make('lines_count')
                    ->label('Jumlah barang')
                    ->counts('lines')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ItemRequest::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ItemRequest::STATUS_PENDING => 'warning',
                        ItemRequest::STATUS_PROCESSING => 'info',
                        ItemRequest::STATUS_COMPLETED => 'success',
                        ItemRequest::STATUS_PARTIAL => 'info',
                        ItemRequest::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('warehouse.name')
                    ->label('Gudang asal')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('destinationWarehouse.name')
                    ->label('Gudang tujuan')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approver.name')
                    ->label('Disetujui oleh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approved_at')
                    ->label('Disetujui pada')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ItemRequest::statusOptions()),
                SelectFilter::make('division_id')
                    ->label('Divisi')
                    ->relationship('division', 'name'),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang asal')
                    ->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                self::approveAction()
                    ->labeledFrom('md'),
                self::rejectAction()
                    ->labeledFrom('md'),
            ])
            ->defaultSort('created_at', 'desc')
            ->infinite(perPage: 50);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Setujui')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ItemRequest $record): bool => $record->isPending())
            ->disabled(fn (): bool => ! self::settingsConfigured())
            ->tooltip(fn (): ?string => self::settingsConfigured()
                ? null
                : 'Atur gudang asal dan tujuan di menu Pengaturan Permintaan terlebih dahulu.')
            ->modalHeading('Setujui permintaan barang')
            ->modalDescription('Tentukan barang mana yang disetujui beserta jumlahnya. Barang yang tidak dicentang akan ditolak.')
            ->modalSubmitActionLabel('Simpan keputusan')
            ->modalWidth(Width::FiveExtraLarge)
            ->fillForm(fn (ItemRequest $record): array => [
                'lines' => self::approvalLineDefaults($record),
            ])
            ->schema(function (): array {
                $settings = self::settings();

                return [
                    Section::make('Tujuan pemindahan')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('origin_warehouse')
                                ->label('Gudang asal')
                                ->state($settings?->warehouse?->name ?? 'Belum diatur'),
                            TextEntry::make('destination_warehouse')
                                ->label('Gudang tujuan')
                                ->state($settings?->destinationWarehouse?->name ?? 'Belum diatur'),
                            Textarea::make('note')
                                ->label('Catatan')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),
                    Section::make('Keputusan per barang')
                        ->description('Jumlah yang disetujui tidak boleh melebihi jumlah yang diminta. Stok barang yang disetujui dipindahkan dari gudang asal ke gudang tujuan.')
                        ->schema([
                            Repeater::make('lines')
                                ->hiddenLabel()
                                ->table([
                                    TableColumn::make('Barang'),
                                    TableColumn::make('Diminta'),
                                    TableColumn::make('Stok asal'),
                                    TableColumn::make('Setujui'),
                                    TableColumn::make('Jumlah disetujui'),
                                    TableColumn::make('Alasan penolakan'),
                                ])
                                ->schema([
                                    Hidden::make('line_id'),
                                    Hidden::make('requested_quantity'),
                                    TextInput::make('item_label')
                                        ->hiddenLabel()
                                        ->disabled()
                                        ->dehydrated(false),
                                    TextInput::make('requested_display')
                                        ->hiddenLabel()
                                        ->disabled()
                                        ->dehydrated(false),
                                    TextInput::make('available_display')
                                        ->hiddenLabel()
                                        ->disabled()
                                        ->dehydrated(false),
                                    Toggle::make('approved')
                                        ->hiddenLabel(),
                                    TextInput::make('quantity')
                                        ->hiddenLabel()
                                        ->numeric()
                                        ->minValue(0.001)
                                        ->maxValue(fn (Get $get): ?float => filled($get('requested_quantity')) ? (float) $get('requested_quantity') : null)
                                        ->required(fn (Get $get): bool => (bool) $get('approved'))
                                        ->disabled(fn (Get $get): bool => ! (bool) $get('approved')),
                                    TextInput::make('rejection_note')
                                        ->hiddenLabel()
                                        ->maxLength(255)
                                        ->required(fn (Get $get): bool => ! (bool) $get('approved'))
                                        ->disabled(fn (Get $get): bool => (bool) $get('approved')),
                                ])
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ]),
                ];
            })
            ->action(function (ItemRequest $record, array $data, Action $action, Component $livewire): void {
                try {
                    app(ItemRequestService::class)->approve($record, $data);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Permintaan gagal diproses')
                        ->body(collect($exception->errors())->flatten()->first())
                        ->persistent()
                        ->send();

                    $action->halt();
                }

                self::refreshAfterAction($record, $livewire, [
                    'status',
                    'approved_by',
                    'warehouse_id',
                    'destination_warehouse_id',
                    'approved_at',
                    'rejected_by',
                    'rejected_at',
                    'rejection_note',
                ]);

                Notification::make()
                    ->success()
                    ->title('Keputusan persetujuan disimpan')
                    ->body(match ($record->status) {
                        ItemRequest::STATUS_COMPLETED => 'Semua barang disetujui dan stok sudah dipindahkan.',
                        ItemRequest::STATUS_PARTIAL => 'Sebagian barang disetujui. Stok barang yang disetujui sudah dipindahkan.',
                        ItemRequest::STATUS_REJECTED => 'Semua barang ditolak. Stok gudang tidak berubah.',
                        default => 'Keputusan persetujuan sudah disimpan.',
                    })
                    ->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (ItemRequest $record): bool => $record->isPending())
            ->modalHeading('Tolak permintaan barang')
            ->modalDescription('Permintaan akan ditandai ditolak. Stok gudang tidak berubah.')
            ->modalSubmitActionLabel('Tolak permintaan')
            ->schema([
                Textarea::make('rejection_note')
                    ->label('Alasan penolakan')
                    ->rows(3)
                    ->required(),
            ])
            ->action(function (ItemRequest $record, array $data, Action $action, Component $livewire): void {
                try {
                    app(ItemRequestService::class)->reject($record, (string) ($data['rejection_note'] ?? ''));
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Permintaan gagal ditolak')
                        ->body(collect($exception->errors())->flatten()->first())
                        ->persistent()
                        ->send();

                    $action->halt();
                }

                self::refreshAfterAction($record, $livewire, [
                    'status',
                    'rejected_by',
                    'rejected_at',
                    'rejection_note',
                ]);

                Notification::make()
                    ->success()
                    ->title('Permintaan ditolak')
                    ->body('Permintaan ditandai ditolak tanpa mengubah stok gudang.')
                    ->send();
            });
    }

    private static function settings(): ?ItemRequestSetting
    {
        return ItemRequestSetting::query()
            ->with(['warehouse', 'destinationWarehouse'])
            ->first();
    }

    private static function settingsConfigured(): bool
    {
        return ItemRequestSetting::query()
            ->whereNotNull('warehouse_id')
            ->whereNotNull('destination_warehouse_id')
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function approvalLineDefaults(ItemRequest $record): array
    {
        $record->loadMissing(['lines.item.baseUom']);

        $warehouseId = self::settings()?->warehouse_id;

        $balances = blank($warehouseId)
            ? collect()
            : InventoryBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('item_id', $record->lines->pluck('item_id')->filter()->all())
                ->pluck('quantity_on_hand', 'item_id');

        return $record->lines
            ->sortBy('id')
            ->map(function (ItemRequestLine $line) use ($balances): array {
                $requested = (float) $line->quantity;
                $available = blank($line->item_id) ? null : (float) ($balances[$line->item_id] ?? 0);
                $canApprove = $available !== null && $available > 0;

                return [
                    'line_id' => $line->getKey(),
                    'item_label' => filled($line->item)
                        ? $line->item->item_code.' — '.$line->item->item_name
                        : ($line->requested_item_name ?? '—'),
                    'requested_quantity' => $requested,
                    'requested_display' => Number::format($requested, maxPrecision: 3, locale: app()->getLocale()),
                    'available_display' => $available === null
                        ? 'Belum dipetakan'
                        : Number::format($available, maxPrecision: 3, locale: app()->getLocale()),
                    'approved' => $canApprove,
                    'quantity' => $canApprove ? min($requested, $available) : $requested,
                    'rejection_note' => $canApprove
                        ? null
                        : ($available === null
                            ? 'Barang belum dipetakan.'
                            : 'Stok gudang asal tidak tersedia.'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $statePaths
     */
    private static function refreshAfterAction(ItemRequest $record, Component $livewire, array $statePaths): void
    {
        $record->refresh();

        if (method_exists($livewire, 'refreshFormData')) {
            $livewire->refreshFormData($statePaths);
        }
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItemRequests::route('/'),
            'edit' => EditItemRequest::route('/{record}/edit'),
        ];
    }
}
