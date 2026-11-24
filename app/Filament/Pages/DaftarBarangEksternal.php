<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\InfiniteSelect;
use App\Models\ExternalItemMapping;
use App\Models\ExternalSyncedBarang;
use App\Models\Item;
use App\Models\ProductionBatch;
use App\Models\Warehouse;
use App\Services\ExternalBarangApiService;
use App\Services\ExternalBarangSyncService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DaftarBarangEksternal extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|UnitEnum|null $navigationGroup = 'Integrasi Eksternal';

    protected static ?string $navigationLabel = 'Barang Eksternal per Tanggal';

    protected static ?string $title = 'Daftar Barang Eksternal (per Tanggal)';

    protected static ?string $slug = 'barang-eksternal-tanggal';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.daftar-barang-eksternal';

    public ?string $tanggalAwal = null;

    public ?string $tanggalAkhir = null;

    public ?string $statusFilter = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filterData = [];

    public function getSubheading(): ?string
    {
        return 'Daftar dan rekap unit helm dari API eksternal yang dikelompokkan berdasarkan tanggal produksi, lengkap dengan status pemetaan barang RSV dan progres sinkronisasi stok.';
    }

    public function mount(): void
    {
        $this->filterForm->fill([
            'tanggal_awal' => null,
            'tanggal_akhir' => null,
            'status_filter' => null,
        ]);
    }

    protected function getForms(): array
    {
        return [
            'filterForm',
        ];
    }

    public function filterForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter Periode Tanggal & Status')
                    ->compact()
                    ->columns(3)
                    ->schema([
                        DatePicker::make('tanggal_awal')
                            ->label('Tanggal Awal')
                            ->placeholder('Semua tanggal awal'),
                        DatePicker::make('tanggal_akhir')
                            ->label('Tanggal Akhir')
                            ->placeholder('Semua tanggal akhir'),
                        Select::make('status_filter')
                            ->label('Filter Status')
                            ->placeholder('Semua Status')
                            ->options(function (ExternalBarangApiService $apiService): array {
                                $rekap = $apiService->getRekapVariant();
                                $statuses = $rekap['statuses'] ?? ['REGISTER', 'FINISHGOOD', 'OUT', 'RETUR', 'BAD'];

                                return array_combine($statuses, $statuses);
                            }),
                    ]),
            ])
            ->statePath('filterData');
    }

    public function applyFilter(): void
    {
        $state = $this->filterForm->getState();
        $this->tanggalAwal = $state['tanggal_awal'] ?? null;
        $this->tanggalAkhir = $state['tanggal_akhir'] ?? null;
        $this->statusFilter = $state['status_filter'] ?? null;
    }

    public function resetFilter(): void
    {
        $this->filterForm->fill([
            'tanggal_awal' => null,
            'tanggal_akhir' => null,
            'status_filter' => null,
        ]);

        $this->tanggalAwal = null;
        $this->tanggalAkhir = null;
        $this->statusFilter = null;
    }

    /**
     * Mengambil data rekap barang eksternal dikelompokkan berdasarkan tanggal.
     *
     * @return array{
     *     groups: array<string, array{
     *         tanggal: string,
     *         tanggal_label: string,
     *         total_units: int,
     *         total_finishgood: int,
     *         total_register: int,
     *         total_synced: int,
     *         variants: array<int, array<string, mixed>>
     *     }>,
     *     statuses: array<int, string>,
     *     overall_total: int,
     *     overall_finishgood: int,
     *     overall_register: int,
     *     overall_synced: int
     * }
     */
    public function getGroupedData(ExternalBarangApiService $apiService): array
    {
        $response = $apiService->getRekapVariant($this->tanggalAwal, $this->tanggalAkhir);
        $rawItems = $response['data'] ?? [];
        $statuses = $response['statuses'] ?? [];

        $mappings = ExternalItemMapping::query()
            ->with('item')
            ->get()
            ->keyBy('external_variant_id');

        $syncedCounts = ExternalSyncedBarang::query()
            ->selectRaw('DATE(external_occurred_at) as tgl, external_variant_id, COUNT(*) as count')
            ->groupBy('tgl', 'external_variant_id')
            ->get();

        $syncedLookup = [];
        foreach ($syncedCounts as $row) {
            $key = ($row->tgl ?? '').'_'.$row->external_variant_id;
            $syncedLookup[$key] = (int) $row->count;
        }

        $groups = [];
        $overallTotal = 0;
        $overallFinishGood = 0;
        $overallRegister = 0;
        $overallSynced = 0;

        foreach ($rawItems as $item) {
            $tanggal = $item['tanggal'] ?? '-';
            $variantId = (int) ($item['variantId'] ?? 0);
            $perStatus = $item['perStatus'] ?? [];
            $totalUnit = (int) ($item['total'] ?? 0);

            // Hitung register vs finishgood (non-register)
            $registerCount = (int) ($perStatus['REGISTER'] ?? 0);
            $finishGoodCount = 0;
            foreach ($perStatus as $statusName => $count) {
                if (strtoupper($statusName) !== 'REGISTER') {
                    $finishGoodCount += (int) $count;
                }
            }

            // Jika filter status dipilih dan baris ini tidak punya unit di status itu, lewati
            if (filled($this->statusFilter)) {
                $statusCount = (int) ($perStatus[$this->statusFilter] ?? 0);
                if ($statusCount === 0) {
                    continue;
                }
            }

            $syncedKey = $tanggal.'_'.$variantId;
            $syncedCount = $syncedLookup[$syncedKey] ?? 0;

            if (! isset($groups[$tanggal])) {
                try {
                    $tanggalLabel = Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, D MMMM Y');
                } catch (\Exception) {
                    $tanggalLabel = $tanggal;
                }

                $groups[$tanggal] = [
                    'tanggal' => $tanggal,
                    'tanggal_label' => $tanggalLabel,
                    'total_units' => 0,
                    'total_finishgood' => 0,
                    'total_register' => 0,
                    'total_synced' => 0,
                    'variants' => [],
                ];
            }

            $groups[$tanggal]['total_units'] += $totalUnit;
            $groups[$tanggal]['total_finishgood'] += $finishGoodCount;
            $groups[$tanggal]['total_register'] += $registerCount;
            $groups[$tanggal]['total_synced'] += $syncedCount;

            $groups[$tanggal]['variants'][] = [
                'variant_id' => $variantId,
                'kode_variant' => $item['kodeVariant'] ?? '-',
                'product' => $item['product'] ?? '-',
                'style' => $item['style'] ?? '-',
                'color' => $item['color'] ?? '-',
                'size' => $item['size'] ?? '-',
                'size_urutan' => $item['sizeUrutan'] ?? 99,
                'per_status' => $perStatus,
                'total' => $totalUnit,
                'finishgood_total' => $finishGoodCount,
                'register_total' => $registerCount,
                'synced_count' => $syncedCount,
                'mapping' => $mappings->get($variantId),
            ];

            $overallTotal += $totalUnit;
            $overallFinishGood += $finishGoodCount;
            $overallRegister += $registerCount;
            $overallSynced += $syncedCount;
        }

        // Urutkan grup tanggal secara descending (terbaru di atas)
        krsort($groups);

        return [
            'groups' => $groups,
            'statuses' => $statuses,
            'overall_total' => $overallTotal,
            'overall_finishgood' => $overallFinishGood,
            'overall_register' => $overallRegister,
            'overall_synced' => $overallSynced,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sinkron_stok_global')
                ->label('Sinkronkan Stok Sekarang')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->form([
                    Select::make('production_batch_id')
                        ->label('Batch Produksi')
                        ->options(fn (): array => ProductionBatch::query()
                            ->where('status', '!=', ProductionBatch::STATUS_CANCELLED)
                            ->orderByDesc('created_at')
                            ->pluck('batch_code', 'id')
                            ->all())
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $warehouseId = ProductionBatch::query()->find($state)?->output_warehouse_id;

                            if (filled($warehouseId)) {
                                $set('warehouse_id', $warehouseId);
                            }
                        })
                        ->helperText('Semua unit yang disinkronkan akan dicatat sebagai hasil produksi batch ini.'),
                    TextInput::make('external_batch_code')
                        ->label('Kode Batch Eksternal (opsional)')
                        ->placeholder('Contoh: BC001')
                        ->helperText('Isi untuk membatasi sinkronisasi pada satu kode batch eksternal. Kosongkan untuk semua batch.'),
                    Select::make('warehouse_id')
                        ->label('Gudang Tujuan')
                        ->options(fn (): array => Warehouse::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn (): mixed => config('services.external_barang.default_warehouse_id')
                            ?? Warehouse::query()->where('is_active', true)->orderBy('name')->value('id'))
                        ->required()
                        ->searchable()
                        ->helperText('Stok unit helm finish good akan ditambahkan ke gudang ini.'),
                    Toggle::make('all_pages')
                        ->label('Tarik Semua Halaman Paginasi')
                        ->default(true),
                    Toggle::make('dry_run')
                        ->label('Simulasi (Dry Run)')
                        ->default(false),
                ])
                ->action(function (array $data, ExternalBarangSyncService $syncService): void {
                    $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
                    $batch = ProductionBatch::query()->findOrFail($data['production_batch_id']);

                    $result = $syncService->sync($warehouse, [
                        'all_pages' => (bool) ($data['all_pages'] ?? true),
                        'dry_run' => (bool) ($data['dry_run'] ?? false),
                        'production_batch_id' => $batch->getKey(),
                        'batch_code' => $data['external_batch_code'] ?? null,
                    ]);

                    Notification::make()
                        ->title($result['dry_run'] ? 'Simulasi Selesai' : 'Sinkronisasi Selesai')
                        ->body($result['message'].' Batch: '.$batch->batch_code.'.')
                        ->success()
                        ->send();
                }),

            Action::make('petakan_varian_modal')
                ->label('Tambah Pemetaan Varian')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->form([
                    TextInput::make('external_variant_id')
                        ->label('ID Varian Eksternal')
                        ->numeric()
                        ->required(),
                    TextInput::make('external_variant_code')
                        ->label('Kode Varian Eksternal (Contoh: WB001)'),
                    TextInput::make('external_product_name')
                        ->label('Nama Produk'),
                    TextInput::make('external_color_name')
                        ->label('Warna'),
                    TextInput::make('external_size_name')
                        ->label('Ukuran'),
                    InfiniteSelect::make('item_id')
                        ->label('Barang RSV Internal')
                        ->required()
                        ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search): array {
                            $query = Item::query()
                                ->where('is_active', true)
                                ->when(filled($search), fn (Builder $query): Builder => $query->where(
                                    fn (Builder $query): Builder => $query
                                        ->where('item_code', 'like', "%{$search}%")
                                        ->orWhere('item_name', 'like', "%{$search}%"),
                                ));

                            $rows = $query->orderBy('item_name')->skip($offset)->take($limit + 1)->get();

                            return [
                                'options' => $rows->take($limit)
                                    ->mapWithKeys(fn (Item $item): array => [$item->getKey() => "{$item->item_code} - {$item->item_name}"])
                                    ->all(),
                                'hasMore' => $rows->count() > $limit,
                            ];
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => Item::query()->find($value)?->item_name),
                ])
                ->action(function (array $data): void {
                    ExternalItemMapping::updateOrCreate(
                        ['external_variant_id' => $data['external_variant_id']],
                        [
                            'item_id' => $data['item_id'],
                            'external_variant_code' => $data['external_variant_code'] ?? null,
                            'external_product_name' => $data['external_product_name'] ?? null,
                            'external_color_name' => $data['external_color_name'] ?? null,
                            'external_size_name' => $data['external_size_name'] ?? null,
                            'is_active' => true,
                        ],
                    );

                    Notification::make()
                        ->success()
                        ->title('Pemetaan Disimpan')
                        ->body('Varian berhasil dipetakan ke barang RSV.')
                        ->send();
                }),
        ];
    }
}
