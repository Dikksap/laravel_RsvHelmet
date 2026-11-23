<?php

namespace Tests\Feature;

use App\Filament\Resources\ExternalItemMappings\Pages\CreateExternalItemMapping;
use App\Filament\Resources\ExternalItemMappings\Pages\ListExternalItemMappings;
use App\Filament\Resources\ExternalSyncedBarangs\Pages\ListExternalSyncedBarangs;
use App\Models\ExternalItemMapping;
use App\Models\ExternalSyncedBarang;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ExternalItemMappingResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_mapping_list_page_can_render(): void
    {
        $item = Item::factory()->create(['item_name' => 'Helm RSV Windbreaker Carbon']);
        ExternalItemMapping::create([
            'item_id' => $item->getKey(),
            'external_variant_id' => 5,
            'external_variant_code' => 'WB005',
            'external_product_name' => 'Windbreaker',
            'external_color_name' => 'Carbon',
            'is_active' => true,
        ]);

        Livewire::test(ListExternalItemMappings::class)
            ->assertOk()
            ->assertSee('WB005')
            ->assertSee('Windbreaker')
            ->assertSee('Carbon');
    }

    public function test_can_create_new_mapping(): void
    {
        $item = Item::factory()->create(['item_name' => 'Helm RSV Windbreaker White']);

        Livewire::test(CreateExternalItemMapping::class)
            ->fillForm([
                'external_variant_id' => 29,
                'external_variant_code' => 'WB029',
                'external_product_name' => 'Windbreaker',
                'external_style_name' => 'Solid',
                'external_color_name' => 'White Glossy',
                'external_size_name' => 'LG',
                'item_id' => $item->getKey(),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('external_item_mappings', [
            'external_variant_id' => 29,
            'external_variant_code' => 'WB029',
            'item_id' => $item->getKey(),
        ]);
    }

    public function test_header_action_tarik_varian_calls_api_and_notifies(): void
    {
        Http::fake([
            '*api/public/barang/rekap-variant*' => Http::response([
                'data' => [
                    [
                        'variantId' => 5,
                        'kodeVariant' => 'WB005',
                        'product' => 'Windbreaker',
                        'color' => 'Carbon',
                    ],
                ],
            ], 200),
        ]);

        Livewire::test(ListExternalItemMappings::class)
            ->callAction('tarik_varian')
            ->assertNotified();
    }

    public function test_header_action_sinkron_stok_executes_sync(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Gudang Finish Good']);
        $item = Item::factory()->create(['item_name' => 'Helm Windbreaker']);

        ExternalItemMapping::create([
            'item_id' => $item->getKey(),
            'external_variant_id' => 5,
            'external_variant_code' => 'WB005',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 110,
                        'kodeBarang' => 'BC001-WB005-181126-0010',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'tanggal' => '2026-11-17T17:19:42.416Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        Livewire::test(ListExternalItemMappings::class)
            ->callAction('sinkron_stok', data: [
                'warehouse_id' => $warehouse->getKey(),
                'limit' => 50,
                'all_pages' => false,
                'dry_run' => false,
            ])
            ->assertNotified();

        $this->assertDatabaseHas('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-181126-0010',
            'item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
        ]);
    }

    public function test_synced_barang_list_page_can_render(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['item_name' => 'Helm RSV FF500']);

        ExternalSyncedBarang::create([
            'kode_barang' => 'BC001-WB005-181126-0099',
            'external_id' => 199,
            'external_variant_id' => 5,
            'item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'status' => 'FINISHGOOD',
            'synced_at' => now(),
        ]);

        Livewire::test(ListExternalSyncedBarangs::class)
            ->assertOk()
            ->assertSee('BC001-WB005-181126-0099');
    }
}
