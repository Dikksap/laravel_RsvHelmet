<?php

namespace App\Filament\Resources\ExternalItemMappings\Pages;

use App\Filament\Resources\ExternalItemMappings\ExternalItemMappingResource;
use App\Models\ExternalItemMapping;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\ExternalBarangApiService;
use App\Services\ExternalBarangSyncService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListExternalItemMappings extends ListRecords
{
    protected static string $resource = ExternalItemMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Pemetaan'),

            Action::make('tarik_varian')
                ->label('Tarik Varian dari API')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (ExternalBarangApiService $apiService): void {
                    $variants = $apiService->fetchAvailableVariants();

                    if (empty($variants)) {
                        Notification::make()
                            ->danger()
                            ->title('Koneksi API Gagal atau Data Kosong')
                            ->body('Tidak dapat mengambil data varian dari API eksternal ('.$apiService->getBaseUrl().'). Pastikan server API aktif dan URL sudah benar di file .env.')
                            ->send();

                        return;
                    }

                    $existingIds = ExternalItemMapping::query()
                        ->pluck('external_variant_id')
                        ->all();

                    $autoMapped = [];
                    $unmapped = [];

                    foreach ($variants as $v) {
                        $variantId = (int) $v['external_variant_id'];
                        if (in_array($variantId, $existingIds, true)) {
                            continue;
                        }

                        $candidateNames = array_filter([
                            trim(($v['external_product_name'] ?? '').' '.($v['external_style_name'] ?? '').' '.($v['external_color_name'] ?? '').' '.($v['external_size_name'] ?? '')),
                            trim(($v['external_product_name'] ?? '').' '.($v['external_color_name'] ?? '').' '.($v['external_size_name'] ?? '')),
                        ]);

                        $matchedItem = null;
                        foreach ($candidateNames as $candidate) {
                            $matchedItem = Item::query()
                                ->where('is_active', true)
                                ->whereRaw('LOWER(TRIM(item_name)) = ?', [strtolower($candidate)])
                                ->first();

                            if ($matchedItem) {
                                break;
                            }
                        }

                        if ($matchedItem) {
                            ExternalItemMapping::create([
                                'item_id' => $matchedItem->getKey(),
                                'external_variant_id' => $variantId,
                                'external_variant_code' => $v['external_variant_code'],
                                'external_product_name' => $v['external_product_name'],
                                'external_style_name' => $v['external_style_name'],
                                'external_color_name' => $v['external_color_name'],
                                'external_size_name' => $v['external_size_name'],
                                'is_active' => true,
                            ]);
                            $autoMapped[] = ($v['external_variant_code'] ?? "ID {$variantId}").' -> '.$matchedItem->item_name;
                            $existingIds[] = $variantId;
                        } else {
                            $unmapped[] = ($v['external_variant_code'] ?? "ID {$variantId}");
                        }
                    }

                    if (! empty($autoMapped)) {
                        $body = 'Berhasil memetakan otomatis '.count($autoMapped).' varian ke barang RSV: '.implode(', ', $autoMapped).'.';
                        if (! empty($unmapped)) {
                            $body .= ' ('.count($unmapped).' varian lainnya belum ada kecocokan nama: '.implode(', ', $unmapped).', silakan petakan manual).';
                        }

                        Notification::make()
                            ->success()
                            ->title('Pemetaan Otomatis Berhasil')
                            ->body($body)
                            ->send();
                    } elseif (! empty($unmapped)) {
                        Notification::make()
                            ->info()
                            ->title('Varian Ditemukan')
                            ->body('Ditemukan '.count($unmapped).' varian baru dari API ('.implode(', ', $unmapped).'). Tidak ada nama yang persis sama, silakan klik "Tambah Pemetaan" untuk memasangkan secara manual.')
                            ->send();
                    } else {
                        Notification::make()
                            ->info()
                            ->title('Semua Varian Sudah Terpetakan')
                            ->body('Seluruh varian yang ada di API eksternal ('.count($variants).' varian) sudah memiliki pemetaan di sistem.')
                            ->send();
                    }
                }),

            Action::make('sinkron_stok')
                ->label('Sinkronkan Stok Sekarang')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->form([
                    Select::make('warehouse_id')
                        ->label('Gudang Tujuan')
                        ->options(fn (): array => Warehouse::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn () => config('services.external_barang.default_warehouse_id')
                            ?? Warehouse::query()->where('is_active', true)->orderBy('name')->value('id'))
                        ->required()
                        ->searchable()
                        ->helperText('Stok unit helm hasil produksi akan ditambahkan ke gudang ini.'),
                    TextInput::make('limit')
                        ->label('Jumlah Per Halaman (Limit)')
                        ->default(100)
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->required(),
                    Toggle::make('all_pages')
                        ->label('Tarik Semua Halaman Paginasi')
                        ->default(true)
                        ->helperText('Jika aktif, sistem akan menelusuri seluruh halaman hingga data terbaru selesai disinkronkan.'),
                    Toggle::make('dry_run')
                        ->label('Simulasi (Dry Run)')
                        ->default(false)
                        ->helperText('Hanya menghitung dan memverifikasi unit tanpa menambah stok nyata ke gudang.'),
                ])
                ->action(function (array $data, ExternalBarangSyncService $syncService): void {
                    $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);

                    $result = $syncService->sync($warehouse, [
                        'limit' => (int) ($data['limit'] ?? 100),
                        'all_pages' => (bool) ($data['all_pages'] ?? true),
                        'dry_run' => (bool) ($data['dry_run'] ?? false),
                    ]);

                    $notification = Notification::make()
                        ->title($result['dry_run'] ? 'Simulasi Selesai' : 'Sinkronisasi Selesai')
                        ->body($result['message']);

                    if ($result['unmapped_count'] > 0) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
        ];
    }
}
