<?php

namespace Tests\Feature;

use App\Filament\Widgets\OutOfStockItemsTable;
use App\Filament\Widgets\RecentMovementsTable;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
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
