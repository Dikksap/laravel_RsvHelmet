<?php

namespace Tests\Feature;

use App\Filament\Pages\DaftarBarangEksternal;
use App\Models\ExternalItemMapping;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DaftarBarangEksternalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_page_renders_with_grouped_dates(): void
    {
        $item = Item::factory()->create(['item_name' => 'Windbreaker Motif Carbon LG']);

        ExternalItemMapping::create([
            'item_id' => $item->getKey(),
            'external_variant_id' => 5,
            'external_variant_code' => 'WB005',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang/rekap-variant*' => Http::response([
                'data' => [
                    [
                        'tanggal' => '2026-11-23',
                        'variantId' => 5,
                        'kodeVariant' => 'WB005',
                        'product' => 'Windbreaker',
                        'style' => 'Motif',
                        'color' => 'Carbon',
                        'size' => 'LG',
                        'sizeUrutan' => 1,
                        'perStatus' => [
                            'REGISTER' => 10,
                            'FINISHGOOD' => 90,
                        ],
                        'total' => 100,
                    ],
                    [
                        'tanggal' => '2026-11-17',
                        'variantId' => 29,
                        'kodeVariant' => 'WB029',
                        'product' => 'Windbreaker',
                        'style' => 'Solid',
                        'color' => 'White Glossy',
                        'size' => 'LG',
                        'sizeUrutan' => 1,
                        'perStatus' => [
                            'OUT' => 50,
                        ],
                        'total' => 50,
                    ],
                ],
                'statuses' => ['REGISTER', 'FINISHGOOD', 'OUT'],
                'total' => 150,
            ], 200),
        ]);

        Livewire::test(DaftarBarangEksternal::class)
            ->assertOk()
            ->assertSee('2026-11-23')
            ->assertSee('2026-11-17')
            ->assertSee('WB005')
            ->assertSee('WB029')
            ->assertSee('Windbreaker Motif Carbon LG')
            ->assertSee('Belum Dimapping');
    }

    public function test_filter_can_be_applied_and_reset(): void
    {
        Http::fake([
            '*api/public/barang/rekap-variant*' => Http::response([
                'data' => [],
                'statuses' => ['REGISTER', 'FINISHGOOD'],
                'total' => 0,
            ], 200),
        ]);

        Livewire::test(DaftarBarangEksternal::class)
            ->fillForm([
                'tanggal_awal' => '2026-11-01',
                'tanggal_akhir' => '2026-11-30',
                'status_filter' => 'FINISHGOOD',
            ], 'filterForm')
            ->call('applyFilter')
            ->assertSet('tanggalAwal', '2026-11-01')
            ->assertSet('tanggalAkhir', '2026-11-30')
            ->assertSet('statusFilter', 'FINISHGOOD')
            ->call('resetFilter')
            ->assertSet('tanggalAwal', null)
            ->assertSet('tanggalAkhir', null)
            ->assertSet('statusFilter', null);
    }

    public function test_header_action_petakan_varian_saves_mapping(): void
    {
        $item = Item::factory()->create(['item_name' => 'Helm RSV Baru']);

        Livewire::test(DaftarBarangEksternal::class)
            ->callAction('petakan_varian_modal', data: [
                'external_variant_id' => 99,
                'external_variant_code' => 'WB099',
                'external_product_name' => 'Windbreaker',
                'external_color_name' => 'Red',
                'external_size_name' => 'XL',
                'item_id' => $item->getKey(),
            ])
            ->assertNotified();

        $this->assertDatabaseHas('external_item_mappings', [
            'external_variant_id' => 99,
            'external_variant_code' => 'WB099',
            'item_id' => $item->getKey(),
        ]);
    }

    public function test_header_action_sinkron_stok_global_triggers_sync(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();

        ExternalItemMapping::create([
            'item_id' => $item->getKey(),
            'external_variant_id' => 5,
            'external_variant_code' => 'WB005',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang/rekap-variant*' => Http::response([
                'data' => [],
                'statuses' => [],
                'total' => 0,
            ], 200),
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 201,
                        'kodeBarang' => 'BC001-WB005-181126-0201',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'tanggal' => '2026-11-23T10:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        Livewire::test(DaftarBarangEksternal::class)
            ->callAction('sinkron_stok_global', data: [
                'warehouse_id' => $warehouse->getKey(),
                'all_pages' => false,
                'dry_run' => false,
            ])
            ->assertNotified();

        $this->assertDatabaseHas('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-181126-0201',
            'warehouse_id' => $warehouse->getKey(),
        ]);
    }
}
