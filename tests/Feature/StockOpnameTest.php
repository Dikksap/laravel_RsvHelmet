<?php

namespace Tests\Feature;

use App\Filament\Exports\StockOpnameLineExporter;
use App\Filament\Imports\StockOpnameLineImporter;
use App\Filament\Resources\StockOpnames\Pages\CreateStockOpname;
use App\Filament\Resources\StockOpnames\Pages\ListStockOpnames;
use App\Filament\Resources\StockOpnames\Pages\ViewStockOpname;
use App\Filament\Resources\StockOpnames\RelationManagers\LinesRelationManager;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\StockOpname;
use App\Models\StockOpnameLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use App\Services\StockOpnameService;
use Filament\Actions\ExportAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_stock_opname_creation_snapshots_all_stockable_items(): void
    {
        $warehouse = Warehouse::factory()->create();
        $stockedItem = Item::factory()->create(['item_name' => 'Barang A']);
        $emptyItem = Item::factory()->create(['item_name' => 'Barang B']);
        Item::factory()->create(['is_stockable' => false]);
        Item::factory()->parent()->create();

        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $stockedItem->getKey(),
            'quantity_on_hand' => 7,
        ]);

        $opname = app(StockOpnameService::class)->create($warehouse, ['note' => 'Hitung bulanan']);

        $this->assertSame(StockOpname::STATUS_DRAFT, $opname->status);
        $this->assertStringStartsWith('OPNAME-'.$warehouse->code, $opname->code);
        $this->assertSame('Hitung bulanan', $opname->note);
        $this->assertSame(2, $opname->lines()->count());

        $this->assertSame(
            '7.000',
            $opname->lines()->where('item_id', $stockedItem->getKey())->value('system_quantity'),
        );
        $this->assertSame(
            '0.000',
            $opname->lines()->where('item_id', $emptyItem->getKey())->value('system_quantity'),
        );
    }

    public function test_stock_opname_can_be_created_through_filament(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();

        Livewire::test(CreateStockOpname::class)
            ->fillForm([
                'warehouse_id' => $warehouse->getKey(),
                'note' => 'Opname akhir bulan',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $opname = StockOpname::query()->sole();

        $this->assertSame($warehouse->getKey(), $opname->warehouse_id);
        $this->assertSame('Opname akhir bulan', $opname->note);
        $this->assertSame(1, $opname->lines()->count());
        $this->assertSame($item->getKey(), $opname->lines()->sole()->item_id);
    }

    public function test_posting_creates_adjustment_and_updates_stock(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();
        $balance = InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 10,
        ]);

        $service = app(StockOpnameService::class);
        $opname = $service->create($warehouse);

        $line = $opname->lines()->where('item_id', $item->getKey())->sole();
        $line->update(['counted_quantity' => 12]);

        $posted = $service->post($opname);

        $this->assertSame(StockOpname::STATUS_COMPLETED, $posted->status);
        $this->assertNotNull($posted->completed_at);
        $this->assertSame($this->authenticatedUser()->getKey(), $posted->completed_by);
        $this->assertSame('12.000', $balance->fresh()->quantity_on_hand);
        $this->assertNotNull($balance->fresh()->last_counted_at);
        $this->assertSame('2.000', $line->fresh()->adjustment_quantity);

        $movement = InventoryMovement::query()->where('stock_opname_id', $opname->getKey())->sole();

        $this->assertSame(InventoryMovement::TYPE_ADJUSTMENT_IN, $movement->type);
        $this->assertSame('2.000', $movement->quantity);
        $this->assertSame($opname->code, $movement->reference);
    }

    public function test_posting_skips_uncounted_lines_and_handles_stock_decrease(): void
    {
        $warehouse = Warehouse::factory()->create();
        $decreasedItem = Item::factory()->create(['item_name' => 'Barang turun']);
        $matchingItem = Item::factory()->create(['item_name' => 'Barang cocok']);
        $uncountedItem = Item::factory()->create(['item_name' => 'Barang belum dihitung']);

        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $decreasedItem->getKey(),
            'quantity_on_hand' => 10,
            'last_counted_at' => null,
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $matchingItem->getKey(),
            'quantity_on_hand' => 5,
            'last_counted_at' => null,
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $uncountedItem->getKey(),
            'quantity_on_hand' => 3,
            'last_counted_at' => null,
        ]);

        $service = app(StockOpnameService::class);
        $opname = $service->create($warehouse);

        $opname->lines()->where('item_id', $decreasedItem->getKey())->update(['counted_quantity' => 7]);
        $opname->lines()->where('item_id', $matchingItem->getKey())->update(['counted_quantity' => 5]);

        $service->post($opname);

        $this->assertSame('7.000', InventoryBalance::query()
            ->where('item_id', $decreasedItem->getKey())
            ->value('quantity_on_hand'));
        $this->assertSame('5.000', InventoryBalance::query()
            ->where('item_id', $matchingItem->getKey())
            ->value('quantity_on_hand'));
        $this->assertSame('3.000', InventoryBalance::query()
            ->where('item_id', $uncountedItem->getKey())
            ->value('quantity_on_hand'));

        $this->assertSame(1, InventoryMovement::query()->where('stock_opname_id', $opname->getKey())->count());
        $this->assertSame(
            InventoryMovement::TYPE_ADJUSTMENT_OUT,
            InventoryMovement::query()->where('stock_opname_id', $opname->getKey())->sole()->type,
        );

        $uncountedLine = $opname->lines()->where('item_id', $uncountedItem->getKey())->sole();

        $this->assertNull($uncountedLine->adjustment_quantity);
        $this->assertNull(InventoryBalance::query()
            ->where('item_id', $uncountedItem->getKey())
            ->value('last_counted_at'));
    }

    public function test_posting_reconciles_against_current_stock(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();
        $balance = InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 10,
        ]);

        $service = app(StockOpnameService::class);
        $opname = $service->create($warehouse);
        $line = $opname->lines()->where('item_id', $item->getKey())->sole();

        app(StockMovementService::class)->record([
            'type' => InventoryMovement::TYPE_IN_PURCHASE,
            'item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'quantity' => 5,
        ]);

        $line->update(['counted_quantity' => 12]);
        $service->post($opname);

        $this->assertSame('12.000', $balance->fresh()->quantity_on_hand);
        $this->assertSame('-3.000', $line->fresh()->adjustment_quantity);

        $adjustment = InventoryMovement::query()
            ->where('stock_opname_id', $opname->getKey())
            ->where('type', InventoryMovement::TYPE_ADJUSTMENT_OUT)
            ->sole();

        $this->assertSame('3.000', $adjustment->quantity);
    }

    public function test_posting_requires_at_least_one_counted_line(): void
    {
        $warehouse = Warehouse::factory()->create();
        Item::factory()->create();

        $service = app(StockOpnameService::class);
        $opname = $service->create($warehouse);

        try {
            $service->post($opname);
            $this->fail('Posting seharusnya gagal tanpa baris yang dihitung.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
        }

        $this->assertSame(StockOpname::STATUS_DRAFT, $opname->fresh()->status);
    }

    public function test_completed_stock_opname_is_locked(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 4,
        ]);

        $service = app(StockOpnameService::class);
        $opname = $service->create($warehouse);
        $opname->lines()->where('item_id', $item->getKey())->update(['counted_quantity' => 4]);
        $service->post($opname);

        try {
            $service->post($opname->fresh());
            $this->fail('Posting ulang seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        try {
            $service->cancel($opname->fresh());
            $this->fail('Pembatalan dokumen selesai seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    public function test_draft_stock_opname_can_be_cancelled(): void
    {
        $warehouse = Warehouse::factory()->create();
        Item::factory()->create();

        $service = app(StockOpnameService::class);
        $opname = $service->create($warehouse);

        $cancelled = $service->cancel($opname);

        $this->assertSame(StockOpname::STATUS_CANCELLED, $cancelled->status);

        try {
            $service->post($cancelled);
            $this->fail('Dokumen yang dibatalkan tidak boleh diposting.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    public function test_counting_a_line_records_counter_and_time(): void
    {
        $line = StockOpnameLine::factory()->create(['system_quantity' => 5]);

        $line->update(['counted_quantity' => 8]);

        $this->assertNotNull($line->fresh()->counted_at);
        $this->assertSame($this->authenticatedUser()->getKey(), $line->fresh()->counted_by);
        $this->assertSame(3.0, $line->fresh()->difference_quantity);

        $line->update(['counted_quantity' => null]);

        $this->assertNull($line->fresh()->counted_at);
        $this->assertNull($line->fresh()->counted_by);
    }

    public function test_stock_opname_can_be_posted_from_the_view_page(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 2,
        ]);

        $opname = app(StockOpnameService::class)->create($warehouse);
        $opname->lines()->where('item_id', $item->getKey())->update(['counted_quantity' => 6]);
        $opname->refresh();

        Livewire::test(ViewStockOpname::class, ['record' => $opname->getRouteKey()])
            ->callAction('post')
            ->assertHasNoActionErrors();

        $this->assertSame(StockOpname::STATUS_COMPLETED, $opname->fresh()->status);
        $this->assertDatabaseHas('inventory_movements', [
            'stock_opname_id' => $opname->getKey(),
            'type' => InventoryMovement::TYPE_ADJUSTMENT_IN,
            'quantity' => 4,
        ]);
    }

    public function test_fill_system_action_copies_system_quantity_to_counted_quantity(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 9,
        ]);

        $opname = app(StockOpnameService::class)->create($warehouse);

        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $opname,
            'pageClass' => ViewStockOpname::class,
        ])
            ->callAction(TestAction::make('fillSystem')->table())
            ->assertSee('value="9"', escape: false);

        $line = $opname->lines()->where('item_id', $item->getKey())->sole();

        $this->assertSame('9.000', $line->counted_quantity);
        $this->assertNotNull($line->counted_at);
        $this->assertSame($this->authenticatedUser()->getKey(), $line->counted_by);
    }

    public function test_counted_quantity_display_removes_trailing_zeros(): void
    {
        $this->assertSame('9', LinesRelationManager::formatQuantity('9.000'));
        $this->assertSame('9.5', LinesRelationManager::formatQuantity('9.500'));
        $this->assertSame('0.001', LinesRelationManager::formatQuantity('0.001'));
        $this->assertSame('0', LinesRelationManager::formatQuantity('0.000'));
        $this->assertNull(LinesRelationManager::formatQuantity(null));
    }

    public function test_lines_can_be_imported_from_csv(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['item_code' => 'IMP-LINE-1']);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 10,
            'last_counted_at' => null,
        ]);

        $opname = app(StockOpnameService::class)->create($warehouse);
        $line = $opname->lines()->where('item_id', $item->getKey())->sole();

        StockOpnameLineImporter::test(null, ['stock_opname_id' => $opname->getKey()])
            ->import([
                'item_code' => 'IMP-LINE-1',
                'counted_quantity' => '12.5',
                'note' => 'Rak A',
            ])
            ->assertImported();

        $line->refresh();

        $this->assertSame('12.500', $line->counted_quantity);
        $this->assertSame('Rak A', $line->note);
        $this->assertNotNull($line->counted_at);
        $this->assertSame($this->authenticatedUser()->getKey(), $line->counted_by);
        $this->assertSame('10.000', $line->system_quantity);
    }

    public function test_lines_can_be_imported_through_the_table_action(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['item_code' => 'IMP-LINE-6']);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 3,
            'last_counted_at' => null,
        ]);
        $opname = app(StockOpnameService::class)->create($warehouse);

        $file = UploadedFile::fake()->createWithContent(
            'hitung-fisik.csv',
            "item_code,counted_quantity,note\nIMP-LINE-6,9,Rak C\n",
        );

        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $opname,
            'pageClass' => ViewStockOpname::class,
        ])
            ->callAction(TestAction::make('import')->table(), data: [
                'file' => $file,
                'columnMap' => [
                    'item_code' => 'item_code',
                    'counted_quantity' => 'counted_quantity',
                    'note' => 'note',
                ],
            ])
            ->assertHasNoActionErrors();

        $line = $opname->lines()->where('item_id', $item->getKey())->sole();

        $this->assertSame('9.000', $line->counted_quantity);
        $this->assertSame('Rak C', $line->note);
        $this->assertNotNull($line->counted_at);
        $this->assertSame($this->authenticatedUser()->getKey(), $line->counted_by);
    }

    public function test_importing_a_blank_count_keeps_the_existing_quantity(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['item_code' => 'IMP-LINE-2']);
        $opname = app(StockOpnameService::class)->create($warehouse);
        $line = $opname->lines()->where('item_id', $item->getKey())->sole();
        $line->update(['counted_quantity' => 4]);

        StockOpnameLineImporter::test(null, ['stock_opname_id' => $opname->getKey()])
            ->import([
                'item_code' => 'IMP-LINE-2',
                'counted_quantity' => '',
                'note' => 'Koreksi catatan',
            ])
            ->assertImported();

        $line->refresh();

        $this->assertSame('4.000', $line->counted_quantity);
        $this->assertSame('Koreksi catatan', $line->note);
    }

    public function test_importing_zero_marks_the_line_as_counted(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['item_code' => 'IMP-LINE-3']);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 6,
            'last_counted_at' => null,
        ]);

        $opname = app(StockOpnameService::class)->create($warehouse);
        $line = $opname->lines()->where('item_id', $item->getKey())->sole();

        StockOpnameLineImporter::test(null, ['stock_opname_id' => $opname->getKey()])
            ->import([
                'item_code' => 'IMP-LINE-3',
                'counted_quantity' => '0',
            ])
            ->assertImported();

        $line->refresh();

        $this->assertSame('0.000', $line->counted_quantity);
        $this->assertNotNull($line->counted_at);
        $this->assertSame(-6.0, $line->difference_quantity);
    }

    public function test_import_fails_for_an_unknown_item_code(): void
    {
        $warehouse = Warehouse::factory()->create();
        $opname = app(StockOpnameService::class)->create($warehouse);

        StockOpnameLineImporter::test(null, ['stock_opname_id' => $opname->getKey()])
            ->import([
                'item_code' => 'TIDAK-ADA',
                'counted_quantity' => '1',
            ])
            ->assertHasRowFailure('Kode barang [TIDAK-ADA] tidak ditemukan.');
    }

    public function test_import_fails_for_an_item_outside_the_opname(): void
    {
        $warehouse = Warehouse::factory()->create();
        $opname = app(StockOpnameService::class)->create($warehouse);
        $outsideItem = Item::factory()->create(['item_code' => 'IMP-LINE-4']);

        StockOpnameLineImporter::test(null, ['stock_opname_id' => $opname->getKey()])
            ->import([
                'item_code' => $outsideItem->item_code,
                'counted_quantity' => '1',
            ])
            ->assertHasRowFailure('Barang [IMP-LINE-4] tidak ada pada daftar hitung fisik opname ini.');
    }

    public function test_import_fails_for_a_completed_opname(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['item_code' => 'IMP-LINE-5']);
        $service = app(StockOpnameService::class);
        $opname = $service->create($warehouse);
        $opname->lines()->where('item_id', $item->getKey())->update(['counted_quantity' => 1]);
        $service->post($opname);

        StockOpnameLineImporter::test(null, ['stock_opname_id' => $opname->getKey()])
            ->import([
                'item_code' => 'IMP-LINE-5',
                'counted_quantity' => '2',
            ])
            ->assertHasRowFailure('Dokumen stok opname sudah tidak berstatus draft.');
    }

    public function test_lines_can_be_exported_to_csv(): void
    {
        $line = StockOpnameLine::factory()->create([
            'system_quantity' => 10,
            'counted_quantity' => 7.5,
            'note' => 'Rak B',
        ]);

        $data = StockOpnameLineExporter::test()->export($line->fresh());

        $this->assertSame($line->item->item_code, $data[0]);
        $this->assertSame($line->item->item_name, $data[1]);
        $this->assertSame('10', $data[2]);
        $this->assertSame('7.5', $data[3]);
        $this->assertSame('-2.5', $data[4]);
        $this->assertNull($data[5]);
        $this->assertSame('Rak B', $data[6]);
        $this->assertSame($this->authenticatedUser()->name, $data[7]);
        $this->assertNotNull($data[8]);
    }

    public function test_line_import_and_export_actions_are_available_only_for_drafts(): void
    {
        $draft = StockOpname::factory()->create();
        $completed = StockOpname::factory()->completed()->create();
        StockOpnameLine::factory()->create(['stock_opname_id' => $draft->getKey()]);
        StockOpnameLine::factory()->create(['stock_opname_id' => $completed->getKey()]);

        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $draft,
            'pageClass' => ViewStockOpname::class,
        ])
            ->mountAction(TestAction::make('import')->table())
            ->assertHasNoActionErrors()
            ->mountAction(TestAction::make('export')->table())
            ->assertHasNoActionErrors();

        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $completed,
            'pageClass' => ViewStockOpname::class,
        ])
            ->assertActionHidden(TestAction::make('import')->table())
            ->assertActionVisible(TestAction::make('export')->table());
    }

    public function test_export_action_exports_all_lines_even_when_the_table_is_filtered(): void
    {
        $warehouse = Warehouse::factory()->create();
        Item::factory()->count(2)->create();
        $opname = app(StockOpnameService::class)->create($warehouse);
        $lines = $opname->lines()->orderBy('id')->get();

        $this->assertGreaterThanOrEqual(2, $lines->count());

        $lines->first()->update(['counted_quantity' => 1]);

        $fake = ExportAction::fake();

        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $opname,
            'pageClass' => ViewStockOpname::class,
        ])
            ->filterTable('count_status', 'pending')
            ->callAction(TestAction::make('export')->table())
            ->assertHasNoActionErrors();

        $fake->assertDispatched(
            StockOpnameLineExporter::class,
            fn ($export, $query): bool => $query->count() === $lines->count(),
        );
    }

    public function test_stock_opname_pages_render_and_can_be_filtered(): void
    {
        $draft = StockOpname::factory()->create();
        $completed = StockOpname::factory()->completed()->create();

        foreach ([
            StockOpnameResource::getUrl('index'),
            StockOpnameResource::getUrl('create'),
            StockOpnameResource::getUrl('view', ['record' => $draft]),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        Livewire::test(ListStockOpnames::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$draft, $completed])
            ->filterTable('status', StockOpname::STATUS_COMPLETED)
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$draft]);
    }

    private function authenticatedUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
