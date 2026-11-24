<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\InfiniteSelect;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemComponent;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class BomBuilder extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?string $navigationLabel = 'Komposisi Barang';

    protected static ?string $title = 'Komposisi Barang';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'bom-builder';

    protected string $view = 'filament.pages.bom-builder';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public int $productionQty = 1;

    public string $tab = 'tree';

    /**
     * Komposisi yang sedang disusun, dikelompokkan per barang induk.
     *
     * @var array<string, array<int, array{component_item_id: string, quantity: float, note: string|null, is_active: bool, sort_order: int}>>
     */
    public array $groups = [];

    /**
     * Barang induk yang komposisinya diubah dan perlu ditulis saat menyimpan.
     *
     * @var array<string, bool>
     */
    public array $dirtyGroups = [];

    /**
     * Barang yang komposisinya disembunyikan di pohon.
     *
     * @var array<int, string>
     */
    public array $collapsed = [];

    public int $visibleLevel = 0;

    public bool $dirty = false;

    public string $search = '';

    /**
     * Barang induk yang sedang dipilih untuk ditambahi komponen (picker inline).
     */
    public ?string $addParentId = null;

    public string $addSearch = '';

    /**
     * @var array<int, array<string, mixed>> | null
     */
    private ?array $allRowsCache = null;

    /**
     * @var array<string, Item> | null
     */
    private ?array $itemCache = null;

    /**
     * @var array<string, mixed> | null
     */
    private ?array $summaryCache = null;

    /**
     * @var array<int, array<string, mixed>> | null
     */
    private ?array $shoppingCache = null;

    public function mount(): void
    {
        $itemId = request()->query('item');

        $item = filled($itemId) ? $this->findBuildableItem($itemId) : null;

        $this->form->fill([
            'parent_item_id' => $item?->getKey(),
        ]);

        $this->loadComposition($item?->getKey(), notify: false);
    }

    public function getSubheading(): ?string
    {
        return 'Susun bahan pembentuk suatu barang dalam satu halaman. Setiap komponen dipakai sebanyak jumlah yang diisi untuk menghasilkan 1 unit barang.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Item::query()
                    ->whereHas('components')
                    ->withCount([
                        'components',
                        'components as active_components_count' => fn (Builder $query): Builder => $query->where('is_active', true),
                    ])
                    ->withMax('components as components_updated_at', 'updated_at')
                    ->with(['type', 'baseUom']),
            )
            ->heading('Barang dengan komposisi')
            ->description('Daftar barang yang sudah memiliki komposisi. Klik Kelola untuk mengedit, atau gunakan komposisinya sebagai template untuk barang lain.')
            ->columns([
                TextColumn::make('item_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item_name')
                    ->label('Nama barang')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('type.name')
                    ->label('Jenis')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('components_count')
                    ->label('Komponen')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('active_components_count')
                    ->label('Aktif')
                    ->badge()
                    ->color(fn (Item $record): string => $record->active_components_count > 0 ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('baseUom.code')
                    ->label('Satuan')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('components_updated_at')
                    ->label('Komposisi diperbarui')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('manage')
                    ->label('Kelola')
                    ->icon('heroicon-o-pencil-square')
                    ->action(function (Item $record): void {
                        $this->loadComposition($record->getKey(), notify: true);
                        $this->js('document.querySelector(".bom-app")?.scrollIntoView({ behavior: "smooth", block: "start" })');
                    }),
                Action::make('useAsTemplate')
                    ->label('Pakai sebagai template')
                    ->icon('heroicon-o-document-duplicate')
                    ->modalHeading('Pakai komposisi sebagai template')
                    ->modalDescription('Pilih barang tujuan. Komponen template akan dimasukkan ke form dan belum disimpan sampai Anda klik Simpan komposisi.')
                    ->modalSubmitActionLabel('Terapkan ke form')
                    ->fillForm(fn (Item $record): array => [
                        'source_item_id' => $record->getKey(),
                        'target_item_id' => $this->defaultTemplateTarget($record->getKey()),
                    ])
                    ->schema($this->templateSchema())
                    ->action(fn (array $data) => $this->applyTemplate($data)),
            ])
            ->headerActions([
                Action::make('createFromTemplate')
                    ->label('Buat dari template')
                    ->icon('heroicon-o-document-duplicate')
                    ->modalHeading('Buat komposisi dari template')
                    ->modalDescription('Pilih barang template dan barang tujuan. Komponen template akan dimasukkan ke form dan belum disimpan sampai Anda klik Simpan komposisi.')
                    ->modalSubmitActionLabel('Terapkan ke form')
                    ->fillForm(fn (): array => [
                        'target_item_id' => $this->defaultTemplateTarget(),
                    ])
                    ->schema($this->templateSchema())
                    ->action(fn (array $data) => $this->applyTemplate($data)),
            ])
            ->defaultSort('item_name')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Belum ada komposisi barang')
            ->emptyStateDescription('Susun komposisi lewat penyusun di bawah. Barang yang sudah punya komposisi akan muncul di sini.');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                InfiniteSelect::make('parent_item_id')
                    ->label('Barang jadi / semi material')
                    ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search): array {
                        $query = Item::query()
                            ->where('is_stockable', true)
                            ->where('has_variants', false)
                            ->when(filled($search), fn (Builder $query): Builder => $query->where(
                                fn (Builder $query): Builder => $query
                                    ->where('item_code', 'like', "%{$search}%")
                                    ->orWhere('item_name', 'like', "%{$search}%"),
                            ))
                            ->with('baseUom');

                        $rows = $query->orderBy('item_name')->skip($offset)->take($limit + 1)->get();

                        return [
                            'options' => $rows->take($limit)
                                ->mapWithKeys(fn (Item $item): array => [
                                    $item->getKey() => $this->itemOptionLabel($item),
                                ])
                                ->all(),
                            'hasMore' => $rows->count() > $limit,
                        ];
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $item = Item::query()->with('baseUom')->find($value);

                        return $item ? $this->itemOptionLabel($item) : null;
                    })
                    ->placeholder('Pilih barang yang akan disusun')
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        $hadUnsavedChanges = $this->dirty;

                        $this->loadComposition($state);

                        if ($hadUnsavedChanges) {
                            Notification::make()
                                ->title('Perubahan sebelumnya dibuang')
                                ->body('Perubahan pada barang sebelumnya belum disimpan.')
                                ->warning()
                                ->send();
                        }
                    })
                    ->helperText('Pilih barang yang akan dibuat komposisinya. Daftar komponen dimuat otomatis dan bisa disunting langsung di pohon komposisi.'),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearComposition')
                ->label('Kosongkan komposisi')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->visible(fn (): bool => $this->rootId() !== null && $this->hasRows($this->rootId()))
                ->requiresConfirmation()
                ->modalHeading('Kosongkan komposisi barang ini?')
                ->modalDescription('Semua komponen akan dihapus saat Anda menyimpan. Perubahan belum tersimpan sampai tombol Simpan diklik.')
                ->action(fn () => $this->clearComposition()),
            Action::make('export')
                ->label('Salin ke Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->rootId() !== null)
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    public function save(): void
    {
        $rootId = $this->rootId();

        if (blank($rootId)) {
            Notification::make()
                ->title('Pilih barang terlebih dahulu')
                ->warning()
                ->send();

            return;
        }

        $error = $this->validationError();

        if ($error !== null) {
            $this->addError('groups', $error);

            Notification::make()
                ->title('Komposisi belum valid')
                ->body($error)
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function (): void {
            foreach (array_keys($this->dirtyGroups) as $parentId) {
                $this->persistGroup((string) $parentId, array_values($this->groups[$parentId] ?? []));
            }
        });

        $this->dirtyGroups = [];
        $this->dirty = false;
        $this->resetCaches();

        Notification::make()
            ->title('Komposisi barang disimpan')
            ->success()
            ->send();
    }

    public function discardChanges(): void
    {
        $rootId = $this->rootId();

        $this->groups = [];
        $this->dirtyGroups = [];
        $this->collapsed = [];
        $this->visibleLevel = 0;
        $this->addParentId = null;
        $this->addSearch = '';
        $this->dirty = false;
        $this->resetCaches();

        if (filled($rootId)) {
            $this->groups = $this->loadGroups($rootId);
        }

        Notification::make()
            ->title('Perubahan dibatalkan')
            ->send();
    }

    public function clearComposition(): void
    {
        $rootId = $this->rootId();

        if (blank($rootId)) {
            return;
        }

        $this->groups[$rootId] = [];
        $this->markDirty($rootId);

        Notification::make()
            ->title('Komposisi dikosongkan di form')
            ->body('Klik Simpan komposisi untuk menghapus seluruh komponen barang ini.')
            ->warning()
            ->send();
    }

    public function updatedProductionQty(): void
    {
        $this->productionQty = max(1, (int) $this->productionQty);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['tree', 'shopping'], true) ? $tab : 'tree';
    }

    public function setVisibleLevel(int $level): void
    {
        $this->visibleLevel = max(0, $level);
        $this->resetCaches();
    }

    public function toggleCollapsed(string $itemId): void
    {
        if (in_array($itemId, $this->collapsed, true)) {
            $this->collapsed = array_values(array_diff($this->collapsed, [$itemId]));
        } else {
            $this->collapsed[] = $itemId;
        }

        $this->resetCaches();
    }

    public function openAdd(?string $parentId): void
    {
        $this->addParentId = $parentId;
        $this->addSearch = '';
    }

    public function closeAdd(): void
    {
        $this->addParentId = null;
        $this->addSearch = '';
    }

    public function addTargetLabel(): ?string
    {
        $itemId = $this->addParentId ?? $this->rootId();

        if (blank($itemId)) {
            return null;
        }

        $item = Item::query()->find($itemId);

        return $item ? "{$item->item_code} — {$item->item_name}" : null;
    }

    public function pickComponent(string $itemId): void
    {
        $parentId = $this->addParentId ?? $this->rootId();

        if (blank($parentId)) {
            return;
        }

        if ($this->addComponent($parentId, $itemId)) {
            $this->closeAdd();
        }
    }

    public function addToRoot(string $itemId): void
    {
        $rootId = $this->rootId();

        if (blank($rootId)) {
            Notification::make()
                ->title('Pilih barang terlebih dahulu')
                ->warning()
                ->send();

            return;
        }

        $this->addComponent($rootId, $itemId);
    }

    public function addComponent(string $parentId, string $itemId, ?int $index = null): bool
    {
        if (blank($parentId)) {
            return false;
        }

        if ($parentId === $itemId) {
            $this->notifyInvalid('Barang tidak boleh menjadi komponen dirinya sendiri.');

            return false;
        }

        if ($this->groupHas($parentId, $itemId)) {
            $this->notifyInvalid('Komponen ini sudah ada di rakitan tersebut.');

            return false;
        }

        if ($this->wouldCreateCycle($parentId, $itemId)) {
            $this->notifyInvalid('Komposisi ini membuat hubungan melingkar antar barang.');

            return false;
        }

        $item = $this->findBuildableItem($itemId);

        if (! $item) {
            return false;
        }

        $this->groups[$parentId] ??= [];
        $rows = $this->groups[$parentId];
        $position = $index === null ? count($rows) : max(0, min($index, count($rows)));

        array_splice($rows, $position, 0, [[
            'component_item_id' => $itemId,
            'quantity' => 1.0,
            'note' => null,
            'is_active' => true,
            'sort_order' => $position,
        ]]);

        $this->groups[$parentId] = $rows;
        $this->reindex($parentId);
        $this->loadDescendants($itemId);
        $this->markDirty($parentId);

        return true;
    }

    public function removeRow(string $parentId, int $index): void
    {
        if (! isset($this->groups[$parentId][$index])) {
            return;
        }

        array_splice($this->groups[$parentId], $index, 1);
        $this->reindex($parentId);
        $this->markDirty($parentId);
    }

    public function updateQuantity(string $parentId, int $index, mixed $value): void
    {
        if (! isset($this->groups[$parentId][$index])) {
            return;
        }

        $quantity = round((float) $value, 3);

        if ($quantity <= 0) {
            $quantity = 0.001;
        }

        $this->groups[$parentId][$index]['quantity'] = $quantity;
        $this->markDirty($parentId);
    }

    public function updateNote(string $parentId, int $index, mixed $value): void
    {
        if (! isset($this->groups[$parentId][$index])) {
            return;
        }

        $note = trim((string) $value);
        $this->groups[$parentId][$index]['note'] = $note === '' ? null : $note;
        $this->markDirty($parentId);
    }

    public function toggleActive(string $parentId, int $index): void
    {
        if (! isset($this->groups[$parentId][$index])) {
            return;
        }

        $this->groups[$parentId][$index]['is_active'] = ! $this->groups[$parentId][$index]['is_active'];
        $this->markDirty($parentId);
    }

    public function moveRow(string $sourceParentId, int $index, string $targetParentId, int $targetIndex, string $position): void
    {
        if ($sourceParentId === $targetParentId && $index === $targetIndex && $position !== 'in') {
            return;
        }

        $row = $this->groups[$sourceParentId][$index] ?? null;

        if (! $row) {
            return;
        }

        $itemId = $row['component_item_id'];

        if ($targetParentId === $itemId) {
            $this->notifyInvalid('Barang tidak boleh menjadi komponen dirinya sendiri.');

            return;
        }

        if ($sourceParentId !== $targetParentId && $this->wouldCreateCycle($targetParentId, $itemId)) {
            $this->notifyInvalid('Komposisi ini membuat hubungan melingkar antar barang.');

            return;
        }

        if ($sourceParentId !== $targetParentId && $this->groupHas($targetParentId, $itemId)) {
            $this->notifyInvalid('Komponen ini sudah ada di rakitan tujuan.');

            return;
        }

        array_splice($this->groups[$sourceParentId], $index, 1);
        $this->reindex($sourceParentId);

        if ($position === 'in') {
            $this->groups[$targetParentId] ??= [];
            $this->groups[$targetParentId][] = $row;
            $this->reindex($targetParentId);
            $this->loadDescendants($itemId);
        } else {
            $target = $targetIndex;

            if ($sourceParentId === $targetParentId && $index < $targetIndex) {
                $target--;
            }

            if ($position === 'after') {
                $target++;
            }

            $rows = $this->groups[$targetParentId] ?? [];
            $target = max(0, min($target, count($rows)));
            array_splice($rows, $target, 0, [$row]);
            $this->groups[$targetParentId] = $rows;
            $this->reindex($targetParentId);
        }

        $this->markDirty($sourceParentId, $targetParentId);
    }

    public function exportCsv(): StreamedResponse
    {
        $root = $this->rootItem();
        $rows = $this->allRows();
        $shopping = $this->shoppingList();
        $summary = $this->summary();

        $fileName = 'komposisi-'.str($root?->item_code ?? 'barang')->slug().'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($root, $rows, $shopping, $summary): void {
            $handle = fopen('php://output', 'w');

            // BOM agar karakter Indonesia tampil benar di Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Komposisi Barang']);
            fputcsv($handle, ['Kode', $root?->item_code ?? '-']);
            fputcsv($handle, ['Nama', $root?->item_name ?? '-']);
            fputcsv($handle, ['Jumlah produksi', $this->productionQty]);
            fputcsv($handle, ['Biaya per unit', $summary['per_unit']]);
            fputcsv($handle, ['Biaya untuk '.$this->productionQty.' unit', $summary['total']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Level', 'No', 'Kode', 'Komponen', 'Jumlah', 'Satuan', 'Harga satuan', 'Subtotal', 'Aktif']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    'L'.$row['level'],
                    $row['number'],
                    $row['item']->item_code,
                    $row['item']->item_name,
                    $row['quantity'],
                    $row['item']->baseUom?->code ?? '-',
                    round($row['unit_cost'], 2),
                    round($row['subtotal'], 2),
                    $row['is_active'] ? 'Ya' : 'Tidak',
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Daftar belanja untuk '.$this->productionQty.' unit']);
            fputcsv($handle, ['Kode', 'Komponen', 'Jumlah total', 'Satuan', 'Harga satuan', 'Perkiraan biaya']);

            foreach ($shopping as $row) {
                fputcsv($handle, [
                    $row['item']->item_code,
                    $row['item']->item_name,
                    $row['quantity'],
                    $row['item']->baseUom?->code ?? '-',
                    round($row['unit_cost'], 2),
                    round($row['subtotal'], 2),
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function loadComposition(?string $itemId, bool $notify = false): void
    {
        if (($this->data['parent_item_id'] ?? null) !== $itemId) {
            $this->form->fill(['parent_item_id' => $itemId]);
        }

        $this->groups = [];
        $this->dirtyGroups = [];
        $this->collapsed = [];
        $this->visibleLevel = 0;
        $this->tab = 'tree';
        $this->addParentId = null;
        $this->addSearch = '';
        $this->dirty = false;
        $this->resetCaches();

        if (blank($itemId)) {
            return;
        }

        $item = $this->findBuildableItem($itemId);

        if (! $item) {
            Notification::make()
                ->title('Barang tidak ditemukan')
                ->danger()
                ->send();

            return;
        }

        $this->groups = $this->loadGroups($item->getKey());

        if (! $notify) {
            return;
        }

        Notification::make()
            ->title('Komposisi dimuat')
            ->body("{$item->item_code} — {$item->item_name}")
            ->success()
            ->send();
    }

    /**
     * Isi form dengan komponen dari barang template tanpa menyimpan ke database.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyTemplate(array $data): void
    {
        $sourceId = $data['source_item_id'] ?? null;
        $targetId = $data['target_item_id'] ?? null;

        if (blank($sourceId) || blank($targetId)) {
            return;
        }

        if ((string) $sourceId === (string) $targetId) {
            Notification::make()
                ->title('Barang tujuan tidak boleh sama dengan barang template.')
                ->danger()
                ->send();

            return;
        }

        $rows = $this->componentsFor($sourceId);

        if ($rows === []) {
            Notification::make()
                ->title('Barang template belum memiliki komponen.')
                ->warning()
                ->send();

            return;
        }

        $this->form->fill(['parent_item_id' => $targetId]);
        $this->loadComposition($targetId);

        $this->groups[$targetId] = $rows;
        $this->markDirty($targetId);

        Notification::make()
            ->title('Template dimasukkan ke form')
            ->body('Silakan edit komponen bila perlu, lalu klik Simpan komposisi. Perubahan belum tersimpan.')
            ->success()
            ->send();
    }

    public function loadTemplate(string $itemId): void
    {
        $item = $this->findBuildableItem($itemId);

        if (! $item) {
            return;
        }

        $this->form->fill(['parent_item_id' => $item->getKey()]);
        $this->loadComposition($item->getKey());
    }

    public function insertTemplate(string $itemId): void
    {
        $rootId = $this->rootId();

        if (blank($rootId)) {
            Notification::make()
                ->title('Pilih barang terlebih dahulu')
                ->warning()
                ->send();

            return;
        }

        $this->addComponent($rootId, $itemId);
    }

    public function rootId(): ?string
    {
        $itemId = $this->data['parent_item_id'] ?? null;

        return filled($itemId) ? (string) $itemId : null;
    }

    public function rootItem(): ?Item
    {
        $rootId = $this->rootId();

        return $rootId !== null ? ($this->itemMap()[$rootId] ?? null) : null;
    }

    /**
     * Baris pohon yang terlihat, sudah memperhitungkan level dan rakitan yang dilipat.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        $rows = $this->allRows();

        if ($this->visibleLevel > 0) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => $row['depth'] < $this->visibleLevel,
            ));
        }

        if ($this->collapsed === []) {
            return $rows;
        }

        $prefixes = [];

        foreach ($this->collapsed as $itemId) {
            foreach ($rows as $row) {
                if ($row['item']->getKey() === $itemId) {
                    $prefixes[] = $row['number'].'.';
                }
            }
        }

        if ($prefixes === []) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            function (array $row) use ($prefixes): bool {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($row['number'], $prefix)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        if ($this->summaryCache !== null) {
            return $this->summaryCache;
        }

        $rootId = $this->rootId();
        $items = $this->itemMap();

        if ($rootId === null || ! isset($items[$rootId])) {
            return $this->summaryCache = [
                'per_unit' => 0.0,
                'total' => 0.0,
                'rows' => 0,
                'direct' => 0,
                'leaves' => 0,
                'max_level' => 0,
            ];
        }

        $memo = [];
        $perUnit = 0.0;

        foreach ($this->activeRows($rootId) as $row) {
            $perUnit += (float) $row['quantity'] * $this->unitCost(
                $row['component_item_id'],
                $items,
                $memo,
                [$rootId => true],
            );
        }

        $allRows = $this->allRows();
        $maxLevel = 0;

        foreach ($allRows as $row) {
            $maxLevel = max($maxLevel, $row['level']);
        }

        return $this->summaryCache = [
            'per_unit' => round($perUnit, 2),
            'total' => round($perUnit * $this->productionQty, 2),
            'rows' => count($allRows),
            'direct' => count($this->groups[$rootId] ?? []),
            'leaves' => count($this->shoppingList()),
            'max_level' => $maxLevel,
        ];
    }

    /**
     * Kebutuhan bahan daun untuk jumlah produksi yang diisi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function shoppingList(): array
    {
        if ($this->shoppingCache !== null) {
            return $this->shoppingCache;
        }

        $rootId = $this->rootId();
        $items = $this->itemMap();

        if ($rootId === null || ! isset($items[$rootId])) {
            return $this->shoppingCache = [];
        }

        $quantities = [];

        $walk = function (string $itemId, float $quantity, array $path) use (&$walk, &$quantities): void {
            if (isset($path[$itemId]) || count($path) >= 25) {
                return;
            }

            $rows = $this->activeRows($itemId);

            if ($rows === []) {
                $quantities[$itemId] = ($quantities[$itemId] ?? 0.0) + $quantity;

                return;
            }

            $path[$itemId] = true;

            foreach ($rows as $row) {
                $walk(
                    $row['component_item_id'],
                    $quantity * (float) $row['quantity'],
                    $path,
                );
            }
        };

        foreach ($this->activeRows($rootId) as $row) {
            $walk(
                $row['component_item_id'],
                (float) $row['quantity'] * $this->productionQty,
                [$rootId => true],
            );
        }

        if ($quantities === []) {
            return $this->shoppingCache = [];
        }

        $stocks = InventoryBalance::query()
            ->whereIn('item_id', array_keys($quantities))
            ->get()
            ->groupBy('item_id')
            ->map(fn ($balances): float => (float) $balances->sum('quantity_on_hand'));

        $rows = [];

        foreach ($quantities as $itemId => $quantity) {
            $item = $items[$itemId] ?? null;

            if (! $item) {
                continue;
            }

            $unitCost = (float) $item->standard_cost;
            $required = round($quantity, 3);

            $rows[] = [
                'item' => $item,
                'quantity' => $required,
                'unit_cost' => $unitCost,
                'subtotal' => round($required * $unitCost, 2),
                'stock' => (float) ($stocks[$itemId] ?? 0.0),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['item']->item_code, $b['item']->item_code));

        return $this->shoppingCache = $rows;
    }

    /**
     * @return array<int, Item>
     */
    public function libraryItems(): array
    {
        $search = trim($this->search);

        return Item::query()
            ->where('is_stockable', true)
            ->where('has_variants', false)
            ->where('is_active', true)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('item_name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%");
                });
            })
            ->with(['baseUom', 'type'])
            ->orderBy('item_name')
            ->limit(100)
            ->get()
            ->all();
    }

    /**
     * @return array<int, Item>
     */
    public function addOptions(): array
    {
        $parentId = $this->addParentId ?? $this->rootId();
        $search = trim($this->addSearch);

        if (blank($parentId)) {
            return [];
        }

        $existing = collect($this->groups[$parentId] ?? [])
            ->pluck('component_item_id')
            ->all();

        return Item::query()
            ->where('is_stockable', true)
            ->where('has_variants', false)
            ->where('is_active', true)
            ->whereKeyNot(array_merge([$parentId], $existing))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('item_name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%");
                });
            })
            ->with(['baseUom', 'type'])
            ->orderBy('item_name')
            ->limit(25)
            ->get()
            ->all();
    }

    /**
     * @return array<int, Item>
     */
    public function templateItems(): array
    {
        return Item::query()
            ->whereHas('components')
            ->when(filled($this->rootId()), fn (Builder $query): Builder => $query->whereKeyNot($this->rootId()))
            ->withCount('components')
            ->with('baseUom')
            ->orderBy('item_name')
            ->limit(50)
            ->get()
            ->all();
    }

    /**
     * @return array<int, array{depth: int, level: int, number: string, key: string, item: Item, quantity: float, unit_cost: float, subtotal: float, note: string|null, is_active: bool, parent_id: string, index: int, has_children: bool, collapsed: bool}>
     */
    public function allRows(): array
    {
        if ($this->allRowsCache !== null) {
            return $this->allRowsCache;
        }

        $rootId = $this->rootId();
        $items = $this->itemMap();

        if ($rootId === null || ! isset($items[$rootId])) {
            return $this->allRowsCache = [];
        }

        $rows = [];
        $memo = [];

        $walk = function (string $parentId, int $depth, string $number, array $ancestors) use (&$walk, $items, &$memo, &$rows): void {
            if (count($ancestors) >= 25) {
                return;
            }

            foreach (array_values($this->groups[$parentId] ?? []) as $index => $component) {
                $itemId = $component['component_item_id'];
                $item = $items[$itemId] ?? null;

                if (! $item || isset($ancestors[$itemId])) {
                    continue;
                }

                $children = $this->activeRows($itemId);
                $childNumber = $number === '' ? (string) ($index + 1) : $number.'.'.($index + 1);
                $unitCost = $this->unitCost($itemId, $items, $memo, $ancestors + [$itemId => true]);

                $rows[] = [
                    'key' => $parentId.'|'.$index,
                    'depth' => $depth,
                    'level' => $depth + 1,
                    'number' => $childNumber,
                    'item' => $item,
                    'quantity' => (float) $component['quantity'],
                    'unit_cost' => $unitCost,
                    'subtotal' => round((float) $component['quantity'] * $unitCost, 2),
                    'note' => $component['note'],
                    'is_active' => (bool) $component['is_active'],
                    'parent_id' => $parentId,
                    'index' => $index,
                    'has_children' => $children !== [],
                    'collapsed' => in_array($itemId, $this->collapsed, true),
                ];

                if ($component['is_active'] && $children !== []) {
                    $walk($itemId, $depth + 1, $childNumber, $ancestors + [$itemId => true]);
                }
            }
        };

        $walk($rootId, 0, '', [$rootId => true]);

        return $this->allRowsCache = $rows;
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, float>  $memo
     * @param  array<string, bool>  $stack
     */
    private function unitCost(string $itemId, array $items, array &$memo, array $stack): float
    {
        if (isset($memo[$itemId])) {
            return $memo[$itemId];
        }

        $item = $items[$itemId] ?? null;

        if (! $item) {
            return 0.0;
        }

        $rows = $this->activeRows($itemId);

        if ($rows === [] || isset($stack[$itemId])) {
            return $memo[$itemId] = (float) $item->standard_cost;
        }

        $stack[$itemId] = true;
        $total = 0.0;

        foreach ($rows as $row) {
            $total += (float) $row['quantity'] * $this->unitCost($row['component_item_id'], $items, $memo, $stack);
        }

        return $memo[$itemId] = round($total, 2);
    }

    /**
     * @return array<int, array{component_item_id: string, quantity: float, note: string|null, is_active: bool, sort_order: int}>
     */
    private function activeRows(string $itemId): array
    {
        return array_values(array_filter(
            $this->groups[$itemId] ?? [],
            fn (array $row): bool => (bool) $row['is_active'] && (float) $row['quantity'] > 0,
        ));
    }

    /**
     * @return array<string, Item>
     */
    private function itemMap(): array
    {
        if ($this->itemCache !== null) {
            return $this->itemCache;
        }

        $ids = array_keys($this->groups);

        foreach ($this->groups as $rows) {
            foreach ($rows as $row) {
                $ids[] = $row['component_item_id'];
            }
        }

        if ($rootId = $this->rootId()) {
            $ids[] = $rootId;
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return $this->itemCache = [];
        }

        return $this->itemCache = Item::query()
            ->with(['baseUom', 'type'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (Item $item): string => $item->getKey())
            ->all();
    }

    /**
     * @return array<string, array<int, array{component_item_id: string, quantity: float, note: string|null, is_active: bool, sort_order: int}>>
     */
    private function loadGroups(string $rootId): array
    {
        $all = ItemComponent::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('parent_item_id');

        $groups = [];

        $walk = function (string $parentId, array $path) use (&$walk, &$groups, $all): void {
            if (isset($path[$parentId]) || count($path) >= 25) {
                return;
            }

            $path[$parentId] = true;

            $components = $all->get($parentId, collect())->values();

            $groups[$parentId] = $components
                ->map(fn (ItemComponent $component): array => [
                    'component_item_id' => $component->component_item_id,
                    'quantity' => (float) $component->quantity,
                    'note' => $component->note,
                    'is_active' => (bool) $component->is_active,
                    'sort_order' => (int) $component->sort_order,
                ])
                ->all();

            foreach ($components as $component) {
                if ($all->has($component->component_item_id)) {
                    $walk($component->component_item_id, $path);
                }
            }
        };

        $walk($rootId, []);

        return $groups;
    }

    private function loadDescendants(string $itemId): void
    {
        foreach ($this->loadGroups($itemId) as $parentId => $rows) {
            if (! array_key_exists($parentId, $this->groups)) {
                $this->groups[$parentId] = $rows;
            }
        }
    }

    private function hasRows(?string $itemId): bool
    {
        return $itemId !== null && ($this->groups[$itemId] ?? []) !== [];
    }

    private function groupHas(string $parentId, string $itemId): bool
    {
        foreach ($this->groups[$parentId] ?? [] as $row) {
            if ($row['component_item_id'] === $itemId) {
                return true;
            }
        }

        return false;
    }

    private function reindex(string $parentId): void
    {
        if (! isset($this->groups[$parentId])) {
            return;
        }

        foreach ($this->groups[$parentId] as $index => $row) {
            $this->groups[$parentId][$index]['sort_order'] = $index;
        }
    }

    private function markDirty(string ...$parentIds): void
    {
        foreach ($parentIds as $parentId) {
            if (filled($parentId)) {
                $this->dirtyGroups[$parentId] = true;
            }
        }

        $this->dirty = $this->dirtyGroups !== [];
        $this->resetCaches();
    }

    private function resetCaches(): void
    {
        $this->allRowsCache = null;
        $this->itemCache = null;
        $this->summaryCache = null;
        $this->shoppingCache = null;
    }

    private function notifyInvalid(string $message): void
    {
        $this->addError('groups', $message);

        Notification::make()
            ->title('Tidak bisa menambahkan komponen')
            ->body($message)
            ->danger()
            ->send();
    }

    private function validationError(): ?string
    {
        $children = $this->effectiveChildren();

        foreach ($this->groups as $parentId => $rows) {
            $seen = [];

            foreach ($rows as $row) {
                $componentId = $row['component_item_id'];

                if ($componentId === $parentId) {
                    return 'Barang tidak boleh menjadi komponen dirinya sendiri.';
                }

                if (isset($seen[$componentId])) {
                    return 'Komponen yang sama dipilih lebih dari sekali dalam satu rakitan.';
                }

                $seen[$componentId] = true;

                if ($row['is_active'] && $this->wouldCreateCycle((string) $parentId, $componentId, $children)) {
                    return 'Komposisi ini membuat hubungan melingkar antar barang.';
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function effectiveChildren(): array
    {
        $children = [];

        foreach (ItemComponent::query()->where('is_active', true)->get(['parent_item_id', 'component_item_id']) as $row) {
            $children[$row->parent_item_id][] = $row->component_item_id;
        }

        foreach ($this->groups as $parentId => $rows) {
            $children[$parentId] = [];

            foreach ($rows as $row) {
                if ($row['is_active']) {
                    $children[$parentId][] = $row['component_item_id'];
                }
            }
        }

        return $children;
    }

    /**
     * @param  array<string, array<int, string>> | null  $children
     */
    private function wouldCreateCycle(string $parentId, string $componentId, ?array $children = null): bool
    {
        if ($parentId === $componentId) {
            return true;
        }

        $children ??= $this->effectiveChildren();

        $stack = [$componentId];
        $visited = [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            foreach ($children[$current] ?? [] as $child) {
                if ($child === $parentId) {
                    return true;
                }

                $stack[] = $child;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{component_item_id: string, quantity: float, note: string|null, is_active: bool, sort_order: int}>  $rows
     */
    private function persistGroup(string $parentId, array $rows): void
    {
        $existing = ItemComponent::query()
            ->where('parent_item_id', $parentId)
            ->get()
            ->keyBy('component_item_id');

        $kept = [];

        foreach ($rows as $position => $row) {
            $payload = [
                'quantity' => round((float) $row['quantity'], 3),
                'note' => $row['note'],
                'is_active' => (bool) $row['is_active'],
                'sort_order' => $position,
            ];

            $component = $existing->get($row['component_item_id']);

            if ($component) {
                $changed = round((float) $component->quantity, 3) !== $payload['quantity']
                    || $component->note !== $payload['note']
                    || (bool) $component->is_active !== $payload['is_active']
                    || (int) $component->sort_order !== $payload['sort_order'];

                if ($changed) {
                    $component->update($payload);
                }
            } else {
                ItemComponent::query()->create([
                    ...$payload,
                    'parent_item_id' => $parentId,
                    'component_item_id' => $row['component_item_id'],
                ]);
            }

            $kept[] = $row['component_item_id'];
        }

        ItemComponent::query()
            ->where('parent_item_id', $parentId)
            ->whereNotIn('component_item_id', $kept)
            ->delete();
    }

    private function findBuildableItem(string $itemId): ?Item
    {
        return Item::query()
            ->where('is_stockable', true)
            ->where('has_variants', false)
            ->find($itemId);
    }

    /**
     * @return array<int, Component>
     */
    private function templateSchema(): array
    {
        return [
            InfiniteSelect::make('source_item_id')
                ->label('Barang template')
                ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search): array {
                    $query = Item::query()
                        ->whereHas('components')
                        ->when(filled($search), fn (Builder $query): Builder => $query->where(
                            fn (Builder $query): Builder => $query
                                ->where('item_code', 'like', "%{$search}%")
                                ->orWhere('item_name', 'like', "%{$search}%"),
                        ))
                        ->withCount('components')
                        ->with('baseUom');

                    $rows = $query->orderBy('item_name')->skip($offset)->take($limit + 1)->get();

                    return [
                        'options' => $rows->take($limit)
                            ->mapWithKeys(fn (Item $item): array => [
                                $item->getKey() => $this->templateItemOptionLabel($item),
                            ])
                            ->all(),
                        'hasMore' => $rows->count() > $limit,
                    ];
                })
                ->getOptionLabelUsing(function ($value): ?string {
                    $item = Item::query()->withCount('components')->with('baseUom')->find($value);

                    return $item ? $this->templateItemOptionLabel($item) : null;
                })
                ->required()
                ->helperText('Hanya barang yang sudah memiliki komposisi yang bisa dipakai sebagai template.'),
            InfiniteSelect::make('target_item_id')
                ->label('Barang tujuan')
                ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search, Get $get): array {
                    $excludeId = $get('source_item_id');

                    $query = Item::query()
                        ->where('is_stockable', true)
                        ->where('has_variants', false)
                        ->when(filled($excludeId), fn (Builder $query): Builder => $query->whereKeyNot($excludeId))
                        ->when(filled($search), fn (Builder $query): Builder => $query->where(
                            fn (Builder $query): Builder => $query
                                ->where('item_code', 'like', "%{$search}%")
                                ->orWhere('item_name', 'like', "%{$search}%"),
                        ))
                        ->with('baseUom');

                    $rows = $query->orderBy('item_name')->skip($offset)->take($limit + 1)->get();

                    return [
                        'options' => $rows->take($limit)
                            ->mapWithKeys(fn (Item $item): array => [
                                $item->getKey() => $this->itemOptionLabel($item),
                            ])
                            ->all(),
                        'hasMore' => $rows->count() > $limit,
                    ];
                })
                ->getOptionLabelUsing(function ($value): ?string {
                    $item = Item::query()->with('baseUom')->find($value);

                    return $item ? $this->itemOptionLabel($item) : null;
                })
                ->required()
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    if (filled($value) && (string) $value === (string) $get('source_item_id')) {
                        $fail('Barang tujuan tidak boleh sama dengan barang template.');
                    }
                })
                ->helperText(function (Get $get): string {
                    $targetId = $get('target_item_id');

                    $count = filled($targetId)
                        ? ItemComponent::query()->where('parent_item_id', $targetId)->count()
                        : 0;

                    return $count > 0
                        ? "Barang tujuan sudah punya {$count} komponen. Menyimpan form akan menggantikan komposisi lama."
                        : 'Komposisi template akan dimasukkan ke form untuk barang ini.';
                }),
        ];
    }

    private function defaultTemplateTarget(?string $exceptId = null): ?string
    {
        $currentParentId = $this->data['parent_item_id'] ?? null;

        if (blank($currentParentId)) {
            return null;
        }

        if (filled($exceptId) && (string) $currentParentId === (string) $exceptId) {
            return null;
        }

        return $currentParentId;
    }

    private function itemOptionLabel(Item $item): string
    {
        $uom = $item->baseUom?->code;

        return "{$item->item_code} — {$item->item_name}".($uom ? " ({$uom})" : '');
    }

    private function templateItemOptionLabel(Item $item): string
    {
        return $this->itemOptionLabel($item)." · {$item->components_count} komponen";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function componentsFor(string $itemId): array
    {
        return ItemComponent::query()
            ->where('parent_item_id', $itemId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn (ItemComponent $component, int $index): array => [
                'component_item_id' => $component->component_item_id,
                'quantity' => (float) $component->quantity,
                'note' => $component->note,
                'is_active' => (bool) $component->is_active,
                'sort_order' => $index,
            ])
            ->all();
    }
}
