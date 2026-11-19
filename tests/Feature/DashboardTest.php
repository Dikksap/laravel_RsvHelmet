<?php

namespace Tests\Feature;

use App\Filament\Pages\BatchRealizationDetail;
use App\Filament\Widgets\BatchProgressChart;
use App\Filament\Widgets\BatchProgressTable;
use App\Filament\Widgets\BatchStatusChart;
use App\Filament\Widgets\ProductionOverview;
use App\Filament\Widgets\RecentCompletedBatchesTable;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BatchProgressService;
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

    public function test_dashboard_renders_production_widgets_and_header_actions(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Dasbor')
            ->assertSee('Progres batch produksi')
            ->assertSee('Laporan realisasi')
            ->assertSee('Batch baru');
    }

    public function test_dashboard_widgets_render_their_titles(): void
    {
        Livewire::test(ProductionOverview::class)
            ->assertSee('Batch berjalan')
            ->assertSee('Progres total');

        Livewire::test(BatchProgressChart::class)
            ->assertSee('Progres tiap batch');

        Livewire::test(BatchStatusChart::class)
            ->assertSee('Status batch');

        Livewire::test(BatchProgressTable::class)
            ->assertSee('Progres batch produksi');

        Livewire::test(RecentCompletedBatchesTable::class)
            ->assertSee('Batch selesai terbaru');
    }

    public function test_summary_counts_only_running_batches_and_completions_this_month(): void
    {
        $item = Item::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $runningBatch = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_RUNNING,
        ]);
        ProductionBatchItem::factory()->create([
            'production_batch_id' => $runningBatch->getKey(),
            'item_id' => $item->getKey(),
            'quantity_planned' => 100,
        ]);
        $this->createMovement($runningBatch, $item, $warehouse, 80);
        $this->createMovement($runningBatch, $item, $warehouse, 30, InventoryMovement::TYPE_OUT_PRODUCTION);

        $cancelledBatch = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_CANCELLED,
        ]);
        ProductionBatchItem::factory()->create([
            'production_batch_id' => $cancelledBatch->getKey(),
            'item_id' => $item->getKey(),
            'quantity_planned' => 500,
        ]);
        $this->createMovement($cancelledBatch, $item, $warehouse, 500);

        ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_COMPLETED,
            'finished_at' => now()->startOfMonth()->toDateString(),
        ]);
        ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_COMPLETED,
            'finished_at' => now()->subMonth()->toDateString(),
        ]);

        $summary = app(BatchProgressService::class)->summary();

        $this->assertSame([
            'running_batches' => 1,
            'planned_quantity' => 100.0,
            'actual_quantity' => 80.0,
            'progress_percentage' => 80.0,
            'completed_this_month' => 1,
        ], $summary);
    }

    public function test_summary_handles_running_batches_without_a_plan(): void
    {
        $batch = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_RUNNING,
        ]);
        $this->createMovement(
            $batch,
            Item::factory()->create(),
            Warehouse::factory()->create(),
            5,
        );

        $this->assertSame([
            'running_batches' => 1,
            'planned_quantity' => 0.0,
            'actual_quantity' => 5.0,
            'progress_percentage' => 0.0,
            'completed_this_month' => 0,
        ], app(BatchProgressService::class)->summary());
    }

    public function test_status_counts_include_every_status_with_zero_counts(): void
    {
        ProductionBatch::factory()->create(['status' => ProductionBatch::STATUS_DRAFT]);
        ProductionBatch::factory()->create(['status' => ProductionBatch::STATUS_RUNNING]);
        ProductionBatch::factory()->create(['status' => ProductionBatch::STATUS_RUNNING]);
        ProductionBatch::factory()->create(['status' => ProductionBatch::STATUS_COMPLETED]);

        $this->assertSame([
            ProductionBatch::STATUS_DRAFT => 1,
            ProductionBatch::STATUS_RUNNING => 2,
            ProductionBatch::STATUS_COMPLETED => 1,
            ProductionBatch::STATUS_CANCELLED => 0,
        ], app(BatchProgressService::class)->statusCounts());
    }

    public function test_recent_completed_returns_only_the_latest_completed_batches_with_totals(): void
    {
        $item = Item::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $newestCompleted = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_COMPLETED,
            'finished_at' => now()->subDay()->toDateString(),
        ]);
        ProductionBatchItem::factory()->create([
            'production_batch_id' => $newestCompleted->getKey(),
            'item_id' => $item->getKey(),
            'quantity_planned' => 100,
        ]);
        $this->createMovement($newestCompleted, $item, $warehouse, 75);

        ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_COMPLETED,
            'finished_at' => now()->subDays(3)->toDateString(),
        ]);
        ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_RUNNING,
            'finished_at' => now()->toDateString(),
        ]);

        $this->assertSame([
            [
                'batch_code' => $newestCompleted->batch_code,
                'finished_at' => now()->subDay()->toDateString(),
                'planned_quantity' => 100.0,
                'actual_quantity' => 75.0,
                'progress_percentage' => 75.0,
            ],
        ], app(BatchProgressService::class)->recentCompleted(1));
    }

    public function test_running_batches_are_limited_to_eight(): void
    {
        ProductionBatch::factory()
            ->count(9)
            ->create(['status' => ProductionBatch::STATUS_RUNNING]);

        $this->assertCount(8, app(BatchProgressService::class)->runningBatches(20));
    }

    public function test_progress_table_defaults_to_draft_and_running_batches_and_can_filter_status(): void
    {
        $draftBatch = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_DRAFT,
        ]);
        $runningBatch = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_RUNNING,
        ]);
        $completedBatch = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_COMPLETED,
        ]);
        $cancelledBatch = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_CANCELLED,
        ]);

        Livewire::test(BatchProgressTable::class)
            ->assertCanSeeTableRecords([$draftBatch, $runningBatch])
            ->assertCanNotSeeTableRecords([$completedBatch, $cancelledBatch])
            ->assertTableFilterExists('status')
            ->assertTableActionHasUrl(
                'detail',
                BatchRealizationDetail::getUrl(['batch' => $runningBatch->getKey()]),
                $runningBatch,
            )
            ->filterTable('status', [ProductionBatch::STATUS_CANCELLED])
            ->assertCanSeeTableRecords([$cancelledBatch])
            ->assertCanNotSeeTableRecords([$draftBatch, $runningBatch, $completedBatch]);
    }

    public function test_recent_completed_batches_table_only_shows_completed_batches(): void
    {
        $completedBatch = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_COMPLETED,
        ]);
        $runningBatch = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_RUNNING,
        ]);
        $cancelledBatch = ProductionBatch::factory()->create([
            'status' => ProductionBatch::STATUS_CANCELLED,
        ]);

        Livewire::test(RecentCompletedBatchesTable::class)
            ->assertCanSeeTableRecords([$completedBatch])
            ->assertCanNotSeeTableRecords([$runningBatch, $cancelledBatch]);
    }

    private function createMovement(
        ProductionBatch $batch,
        Item $item,
        Warehouse $warehouse,
        float $quantity,
        string $type = InventoryMovement::TYPE_IN_PRODUCTION,
    ): void {
        InventoryMovement::factory()->create([
            'production_batch_id' => $batch->getKey(),
            'item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'quantity' => $quantity,
            'type' => $type,
        ]);
    }
}
