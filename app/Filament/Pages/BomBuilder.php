<?php

namespace App\Filament\Pages;

use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemComponent;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
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

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $itemId = request()->query('item');

        $item = filled($itemId)
            ? Item::query()
                ->where('is_stockable', true)
                ->where('has_variants', false)
                ->find($itemId)
            : null;

        $this->form->fill([
            'parent_item_id' => $item?->getKey(),
            'components' => $item ? $this->componentsFor($item->getKey()) : [],
        ]);
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
                    ->action(fn (Item $record) => $this->loadComposition($record->getKey())),
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
            ->emptyStateDescription('Susun komposisi lewat form di bawah. Barang yang sudah punya komposisi akan muncul di sini.');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Barang yang disusun')
                    ->columns(2)
                    ->schema([
                        Select::make('parent_item_id')
                            ->label('Barang jadi / semi material')
                            ->options(fn (): array => $this->itemOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('components', $state ? $this->componentsFor($state) : []);
                            })
                            ->helperText('Pilih barang yang akan dibuat komposisinya. Daftar komponen akan dimuat otomatis.'),
                        TextEntry::make('parent_item_info')
                            ->label('Informasi barang')
                            ->state(function (Get $get): string {
                                $item = Item::with(['type', 'baseUom'])->find($get('parent_item_id'));

                                if (! $item) {
                                    return 'Belum ada barang yang dipilih.';
                                }

                                $uom = $item->baseUom?->code ?? $item->baseUom?->name ?? '—';

                                return "{$item->item_code} — {$item->item_name} · {$item->type?->name} · satuan {$uom}";
                            }),
                    ]),
                Section::make('Daftar komponen')
                    ->description('Tambahkan beberapa baris sekaligus, lalu klik Simpan komposisi. Baris yang dihapus akan ikut dihapus dari database.')
                    ->visible(fn (Get $get): bool => filled($get('parent_item_id')))
                    ->schema([
                        Repeater::make('components')
                            ->hiddenLabel()
                            ->addActionLabel('Tambah komponen')
                            ->defaultItems(0)
                            ->columns(12)
                            ->schema([
                                Select::make('component_item_id')
                                    ->label('Komponen')
                                    ->options(fn (Get $get): array => $this->itemOptions(
                                        excludeId: $get('../../parent_item_id'),
                                    ))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(5)
                                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        $parentId = $get('../../parent_item_id');

                                        if (blank($parentId) || blank($value)) {
                                            return;
                                        }

                                        if ((string) $parentId === (string) $value) {
                                            $fail('Barang tidak boleh menjadi komponen dirinya sendiri.');

                                            return;
                                        }

                                        if (ItemComponent::wouldCreateCycle((string) $parentId, (string) $value)) {
                                            $fail('Komposisi ini membuat hubungan melingkar antar barang.');
                                        }
                                    })
                                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        if (blank($value)) {
                                            return;
                                        }

                                        $duplicates = collect($get('../../components') ?? [])
                                            ->filter(fn (mixed $row): bool => (($row['component_item_id'] ?? null) === $value))
                                            ->count();

                                        if ($duplicates > 1) {
                                            $fail('Komponen ini sudah dipilih di baris lain.');
                                        }
                                    }),
                                TextInput::make('quantity')
                                    ->label('Jumlah per 1 unit')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(2),
                                TextEntry::make('available_stock')
                                    ->label('Stok total')
                                    ->state(function (Get $get): string {
                                        $itemId = $get('component_item_id');

                                        if (blank($itemId)) {
                                            return '—';
                                        }

                                        $available = InventoryBalance::query()
                                            ->where('item_id', $itemId)
                                            ->get()
                                            ->sum(fn (InventoryBalance $balance): float => (float) $balance->quantity_on_hand);

                                        return Number::format($available, maxPrecision: 3, locale: app()->getLocale());
                                    })
                                    ->columnSpan(2),
                                Textarea::make('note')
                                    ->label('Catatan')
                                    ->rows(1)
                                    ->columnSpan(2),
                                Toggle::make('is_active')
                                    ->label('Aktif')
                                    ->default(true)
                                    ->columnSpan(1),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $item = Item::find($state['component_item_id'] ?? null);

                                return $item ? "{$item->item_code} — {$item->item_name}" : null;
                            }),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Simpan komposisi')
                        ->icon('heroicon-o-check')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ])->key('form-actions'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $parent = Item::query()->findOrFail($data['parent_item_id']);
        $rows = $data['components'] ?? [];

        DB::transaction(function () use ($parent, $rows): void {
            $existing = $parent->components()->get()->keyBy('component_item_id');
            $keptIds = [];

            foreach ($rows as $row) {
                $componentItemId = $row['component_item_id'];
                $payload = [
                    'quantity' => $row['quantity'],
                    'note' => $row['note'] ?? null,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ];

                /** @var ItemComponent|null $component */
                $component = $existing->get($componentItemId);

                if ($component) {
                    $component->update($payload);
                } else {
                    $component = $parent->components()->create([
                        ...$payload,
                        'component_item_id' => $componentItemId,
                    ]);
                }

                $keptIds[] = $component->getKey();
            }

            $parent->components()->whereNotIn('id', $keptIds)->delete();
        });

        $this->form->fill([
            'parent_item_id' => $parent->getKey(),
            'components' => $this->componentsFor($parent->getKey()),
        ]);

        Notification::make()
            ->title('Komposisi barang disimpan')
            ->success()
            ->send();
    }

    public function loadComposition(string $itemId): void
    {
        $item = Item::query()
            ->where('is_stockable', true)
            ->where('has_variants', false)
            ->find($itemId);

        if (! $item) {
            Notification::make()
                ->title('Barang tidak ditemukan')
                ->danger()
                ->send();

            return;
        }

        $this->form->fill([
            'parent_item_id' => $item->getKey(),
            'components' => $this->componentsFor($item->getKey()),
        ]);

        Notification::make()
            ->title('Komposisi dimuat ke form')
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

        $this->form->fill([
            'parent_item_id' => $targetId,
            'components' => $rows,
        ]);

        Notification::make()
            ->title('Template dimasukkan ke form')
            ->body('Silakan edit komponen bila perlu, lalu klik Simpan komposisi. Perubahan belum tersimpan.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Component>
     */
    private function templateSchema(): array
    {
        return [
            Select::make('source_item_id')
                ->label('Barang template')
                ->options(fn (): array => $this->templateItemOptions())
                ->searchable()
                ->required()
                ->helperText('Hanya barang yang sudah memiliki komposisi yang bisa dipakai sebagai template.'),
            Select::make('target_item_id')
                ->label('Barang tujuan')
                ->options(fn (Get $get): array => $this->itemOptions(excludeId: $get('source_item_id')))
                ->searchable()
                ->required()
                ->rules(['different:source_item_id'])
                ->validationMessages([
                    'different' => 'Barang tujuan tidak boleh sama dengan barang template.',
                ])
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

    /**
     * @return array<string, string>
     */
    private function templateItemOptions(): array
    {
        return Item::query()
            ->whereHas('components')
            ->withCount('components')
            ->with('baseUom')
            ->orderBy('item_name')
            ->get()
            ->mapWithKeys(function (Item $item): array {
                $uom = $item->baseUom?->code;

                return [$item->getKey() => "{$item->item_code} — {$item->item_name}".($uom ? " ({$uom})" : '')." · {$item->components_count} komponen"];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function itemOptions(?string $excludeId = null): array
    {
        return Item::query()
            ->where('is_stockable', true)
            ->where('has_variants', false)
            ->when(filled($excludeId), fn ($query) => $query->whereKeyNot($excludeId))
            ->with('baseUom')
            ->orderBy('item_name')
            ->get()
            ->mapWithKeys(function (Item $item): array {
                $uom = $item->baseUom?->code;

                return [$item->getKey() => "{$item->item_code} — {$item->item_name}".($uom ? " ({$uom})" : '')];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function componentsFor(string $itemId): array
    {
        return ItemComponent::query()
            ->where('parent_item_id', $itemId)
            ->orderBy('id')
            ->get()
            ->map(fn (ItemComponent $component): array => [
                'component_item_id' => $component->component_item_id,
                'quantity' => (float) $component->quantity,
                'note' => $component->note,
                'is_active' => $component->is_active,
            ])
            ->all();
    }
}
