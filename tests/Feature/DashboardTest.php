<?php

namespace Tests\Feature;

use App\Filament\Widgets\DashboardStatsOverview;
use App\Filament\Widgets\OutOfStockItemsTable;
use App\Filament\Widgets\RecentMovementsTable;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemRequest;
use App\Models\ProductionBatch;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_dashboard_renders_without_production_content(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Dasbor')
            ->assertDontSee('Progres batch produksi')
            ->assertDontSee('Laporan realisasi')
            ->assertDontSee('Batch baru');
    }

    public function test_dashboard_stats_overview_shows_counts(): void
    {
        $item = Item::factory()->create();
        Item::factory()->count(2)->create();
        $warehouse = Warehouse::factory()->create();

        InventoryMovement::factory()->count(2)->create([
            'item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'occurred_at' => now(),
        ]);
        InventoryMovement::factory()->create([
            'item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'occurred_at' => now()->subDay(),
        ]);

        ProductionBatch::factory()->create(['status' => ProductionBatch::STATUS_RUNNING]);
        ProductionBatch::factory()->create(['status' => ProductionBatch::STATUS_COMPLETED]);

        ItemRequest::factory()->count(3)->create();
        ItemRequest::factory()->create(['status' => ItemRequest::STATUS_PROCESSING]);

        Livewire::test(DashboardStatsOverview::class)
            ->assertSeeText('Total barang')
            ->assertSeeText('3')
            ->assertSeeText('Transaksi hari ini')
            ->assertSeeText('2')
            ->assertSeeText('Batch berjalan')
            ->assertSeeText('1')
            ->assertSeeText('Permintaan baru')
            ->assertSeeText('4');
    }

    public function test_dashboard_widgets_render_their_titles(): void
    {
        Livewire::test(RecentMovementsTable::class)
            ->assertSee('Transaksi terbaru');

        Livewire::test(OutOfStockItemsTable::class)
            ->assertSee('Item habis');
    }

    public function test_recent_movements_widget_lists_newest_first_and_limits_rows(): void
    {
        $item = Item::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $movements = collect(range(1, 9))->map(fn (int $day): InventoryMovement => InventoryMovement::factory()->create([
            'item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'occurred_at' => now()->subDays($day),
        ]));

        Livewire::test(RecentMovementsTable::class)
            ->assertCanSeeTableRecords($movements->take(8), inOrder: true)
            ->assertCanNotSeeTableRecords([$movements->last()]);
    }

    public function test_out_of_stock_widget_lists_only_out_and_low_stock(): void
    {
        $out = InventoryBalance::factory()->create([
            'quantity_on_hand' => 0,
            'minimum_quantity' => 10,
        ]);
        $low = InventoryBalance::factory()->create([
            'quantity_on_hand' => 5,
            'minimum_quantity' => 10,
        ]);
        $safe = InventoryBalance::factory()->create([
            'quantity_on_hand' => 50,
            'minimum_quantity' => 10,
        ]);

        Livewire::test(OutOfStockItemsTable::class)
            ->assertCanSeeTableRecords([$out, $low])
            ->assertCanNotSeeTableRecords([$safe]);
    }
}
