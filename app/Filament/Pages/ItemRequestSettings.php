<?php

namespace App\Filament\Pages;

use App\Models\ItemRequestSetting;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class ItemRequestSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Permintaan Barang';

    protected static ?string $navigationLabel = 'Pengaturan Permintaan';

    protected static ?string $title = 'Pengaturan Permintaan Barang';

    protected static ?string $slug = 'pengaturan-permintaan';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.item-request-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = ItemRequestSetting::current();

        $this->form->fill([
            'warehouse_id' => $settings->warehouse_id,
            'destination_warehouse_id' => $settings->destination_warehouse_id,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Gudang default untuk approval')
                    ->description('Saat permintaan barang disetujui, stok otomatis dipindahkan dari gudang asal ke gudang tujuan ini tanpa memilih ulang setiap approval.')
                    ->columns(2)
                    ->schema([
                        Select::make('warehouse_id')
                            ->label('Gudang asal')
                            ->options(fn (): array => $this->warehouseOptions())
                            ->required()
                            ->searchable()
                            ->helperText('Stok barang yang diminta akan dikurangi dari gudang ini.'),
                        Select::make('destination_warehouse_id')
                            ->label('Gudang tujuan')
                            ->options(fn (): array => $this->warehouseOptions())
                            ->required()
                            ->searchable()
                            ->different('warehouse_id')
                            ->validationMessages([
                                'different' => 'Gudang tujuan harus berbeda dari gudang asal.',
                            ])
                            ->helperText('Stok akan ditambahkan ke gudang ini.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        ItemRequestSetting::current()->update([
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'destination_warehouse_id' => $data['destination_warehouse_id'] ?? null,
        ]);

        Notification::make()
            ->success()
            ->title('Pengaturan disimpan')
            ->body('Gudang asal dan gudang tujuan approval sudah diperbarui.')
            ->send();
    }

    /**
     * @return array<int, string>
     */
    private function warehouseOptions(): array
    {
        return Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
