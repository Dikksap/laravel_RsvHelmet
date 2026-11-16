<?php

namespace Tests\Feature;

use App\Filament\Exports\ItemExporter;
use App\Filament\Imports\ItemImporter;
use App\Filament\Pages\BatchRealization;
use App\Filament\Pages\BomBuilder;
use App\Filament\Pages\StockCard;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\InventoryBalances\InventoryBalanceResource;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\ItemAttributes\ItemAttributeResource;
use App\Filament\Resources\ItemCategories\ItemCategoryResource;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Items\RelationManagers\VariantMappingsRelationManager;
use App\Filament\Resources\ItemTypes\ItemTypeResource;
use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Filament\Resources\Uoms\UomResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemCategory;
use App\Models\ItemType;
use App\Models\ItemVariantMapping;
use App\Models\Uom;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_master_data_resources_are_grouped_under_data_master(): void
    {
        $resources = [
            ItemResource::class => 'Barang',
            ItemCategoryResource::class => 'Kategori Barang',
            ItemTypeResource::class => 'Jenis Barang',
            BrandResource::class => 'Merek',
            ItemAttributeResource::class => 'Atribut Barang',
            UomResource::class => 'Satuan',
            WarehouseResource::class => 'Daftar Gudang',
        ];

        foreach ($resources as $resource => $navigationLabel) {
            $this->assertSame('Data Master', $resource::getNavigationGroup());
            $this->assertNull($resource::getNavigationParentItem());
            $this->assertSame($navigationLabel, $resource::getNavigationLabel());
            $this->assertNotNull($resource::getNavigationIcon());
        }
    }

    public function test_stock_and_production_navigation_groups_are_separated(): void
    {
        $resources = [
            InventoryBalanceResource::class => ['Gudang', 'Stok Gudang'],
            InventoryMovementResource::class => ['Gudang', 'Transaksi Stok'],
            StockCard::class => ['Gudang', 'Kartu Stok'],
            ProductionBatchResource::class => ['Produksi', 'Batch Produksi'],
            BomBuilder::class => ['Produksi', 'Komposisi Barang'],
            BatchRealization::class => ['Produksi', 'Laporan Realisasi'],
        ];

        foreach ($resources as $resource => [$group, $label]) {
            $this->assertSame($group, $resource::getNavigationGroup());
            $this->assertSame($label, $resource::getNavigationLabel());
            $this->assertNotNull($resource::getNavigationIcon());
        }
    }

    public function test_navigation_sort_orders_are_unique_within_each_group(): void
    {
        $resources = array_merge(
            Filament::getPanel('admin')->getResources(),
            Filament::getPanel('admin')->getPages(),
        );

        $grouped = [];

        foreach ($resources as $resource) {
            if (! $resource::shouldRegisterNavigation()) {
                continue;
            }

            $group = $resource::getNavigationGroup();

            $this->assertNotContains(
                $resource::getNavigationSort(),
                array_column($grouped[$group] ?? [], 'sort'),
                "Urutan navigasi ganda pada grup [{$group}].",
            );

            $grouped[$group][] = [
                'sort' => $resource::getNavigationSort(),
                'label' => $resource::getNavigationLabel(),
            ];
        }
    }

    public function test_admin_sidebar_renders_grouped_navigation(): void
    {
        $response = $this->get('/admin');

        $response->assertOk();

        foreach ([
            'Gudang',
            'Produksi',
            'Data Master',
            'Stok Gudang',
            'Transaksi Stok',
            'Kartu Stok',
            'Batch Produksi',
            'Komposisi Barang',
            'Laporan Realisasi',
            'Barang',
            'Daftar Gudang',
        ] as $label) {
            $response->assertSee($label);
        }

        $response->assertDontSee('Ringkasan Gudang');
        $response->assertSee('wire:navigate', escape: false);
    }

    public function test_admin_panel_navigates_without_full_page_reloads(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasSpaMode());
        $this->assertFalse($panel->isSidebarCollapsibleOnDesktop());
        $this->assertFalse($panel->isSidebarFullyCollapsibleOnDesktop());
    }

    public function test_admin_panel_uses_indonesian_locale(): void
    {
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('Gudang', InventoryBalanceResource::getNavigationGroup());
        $this->assertSame('Stok Gudang', InventoryBalanceResource::getNavigationLabel());
    }

    public function test_resource_index_pages_render(): void
    {
        $urls = [
            '/admin/items',
            '/admin/item-categories',
            '/admin/item-types',
            '/admin/brands',
            '/admin/item-attributes',
            '/admin/uoms',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_resource_create_pages_render(): void
    {
        $urls = [
            '/admin/items/create',
            '/admin/item-categories/create',
            '/admin/item-types/create',
            '/admin/brands/create',
            '/admin/item-attributes/create',
            '/admin/uoms/create',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_resource_edit_pages_render(): void
    {
        $item = Item::factory()->create();
        $attribute = ItemAttribute::factory()->create();

        $urls = [
            "/admin/items/{$item->getKey()}/edit",
            '/admin/item-categories/'.ItemCategory::factory()->create()->getKey().'/edit',
            '/admin/item-types/'.ItemType::factory()->create()->getKey().'/edit',
            '/admin/brands/'.Brand::factory()->create()->getKey().'/edit',
            "/admin/item-attributes/{$attribute->getKey()}/edit",
            '/admin/uoms/'.Uom::factory()->create()->getKey().'/edit',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_items_can_be_created_through_filament(): void
    {
        $type = ItemType::factory()->create();
        $uom = Uom::factory()->create();

        Livewire::test(CreateItem::class)
            ->fillForm([
                'item_code' => 'HELM-001',
                'item_name' => 'Helmet Test',
                'item_type_id' => $type->getKey(),
                'base_uom_id' => $uom->getKey(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('items', [
            'item_code' => 'HELM-001',
            'item_name' => 'Helmet Test',
        ]);
    }

    public function test_item_import_and_export_action_modals_open(): void
    {
        Livewire::test(ListItems::class)
            ->mountAction(ImportAction::class)
            ->assertHasNoActionErrors();

        Livewire::test(ListItems::class)
            ->mountAction(ExportAction::class)
            ->assertHasNoActionErrors();
    }

    public function test_items_can_be_imported(): void
    {
        $type = ItemType::factory()->create(['name' => 'Product']);
        Uom::factory()->create(['code' => 'PCS', 'name' => 'Piece']);
        ItemCategory::factory()->create(['name' => 'Helm']);

        ItemImporter::test()
            ->import([
                'item_code' => 'IMP-001',
                'item_name' => 'Imported Helmet',
                'item_type_id' => 'Product',
                'category_id' => 'Helm',
                'base_uom_id' => 'PCS',
                'base_price' => '150000',
                'is_active' => '1',
            ])
            ->assertImported();

        $item = Item::query()->where('item_code', 'IMP-001')->firstOrFail();

        $this->assertSame('Imported Helmet', $item->item_name);
        $this->assertTrue($item->type->is($type));
        $this->assertSame('Helm', $item->category->name);
        $this->assertSame('PCS', $item->baseUom->code);
        $this->assertSame('150000.00', $item->base_price);
        $this->assertTrue($item->is_active);
    }

    public function test_items_are_updated_when_imported_with_an_existing_item_code(): void
    {
        $type = ItemType::factory()->create(['name' => 'Product']);
        Uom::factory()->create(['code' => 'PCS', 'name' => 'Piece']);

        Item::factory()->create([
            'item_code' => 'IMP-002',
            'item_name' => 'Old name',
            'item_type_id' => $type->getKey(),
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        ItemImporter::test()
            ->import([
                'item_code' => 'IMP-002',
                'item_name' => 'New name',
                'item_type_id' => 'Product',
                'base_uom_id' => 'PCS',
            ])
            ->assertImported();

        $this->assertSame(1, Item::query()->where('item_code', 'IMP-002')->count());
        $this->assertSame('New name', Item::query()->where('item_code', 'IMP-002')->value('item_name'));
    }

    public function test_items_can_be_exported(): void
    {
        $item = Item::factory()->create([
            'item_code' => 'EXP-001',
            'item_name' => 'Exported Helmet',
        ]);

        $data = ItemExporter::test()->export($item);

        $this->assertSame('EXP-001', $data[0]);
        $this->assertSame('Exported Helmet', $data[2]);
    }

    public function test_items_can_be_created_as_a_parent_and_variant(): void
    {
        $parent = Item::factory()->parent()->create();

        $child = Item::factory()
            ->variantOf($parent)
            ->create();

        $attribute = ItemAttribute::factory()->create();
        $value = $attribute->values()->create([
            'value' => 'Red',
            'code' => 'RD',
        ]);

        ItemVariantMapping::create([
            'item_id' => $child->getKey(),
            'attribute_id' => $attribute->getKey(),
            'attribute_value_id' => $value->getKey(),
        ]);

        $this->assertTrue($parent->has_variants);
        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains($child));
        $this->assertTrue($child->attributeValues->contains($value));
    }

    public function test_variant_mapping_can_be_created_for_an_unused_attribute(): void
    {
        $item = Item::factory()->create();
        $attribute = ItemAttribute::factory()->create();
        $value = $attribute->values()->create([
            'value' => 'Red',
            'code' => 'RD',
        ]);

        Livewire::test(VariantMappingsRelationManager::class, [
            'ownerRecord' => $item,
            'pageClass' => EditItem::class,
        ])
            ->callTableAction(CreateAction::class, data: [
                'attribute_id' => $attribute->getKey(),
                'attribute_value_id' => $value->getKey(),
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $item->variantMappings()->count());
        $this->assertSame($attribute->getKey(), $item->variantMappings()->sole()->attribute_id);
    }

    public function test_variant_mapping_cannot_duplicate_an_attribute_for_the_same_item(): void
    {
        $item = Item::factory()->create();
        $attribute = ItemAttribute::factory()->create();
        $value = $attribute->values()->create([
            'value' => 'Red',
            'code' => 'RD',
        ]);

        ItemVariantMapping::create([
            'item_id' => $item->getKey(),
            'attribute_id' => $attribute->getKey(),
            'attribute_value_id' => $value->getKey(),
        ]);

        Livewire::test(VariantMappingsRelationManager::class, [
            'ownerRecord' => $item,
            'pageClass' => EditItem::class,
        ])
            ->callTableAction(CreateAction::class, data: [
                'attribute_id' => $attribute->getKey(),
                'attribute_value_id' => $value->getKey(),
            ])
            ->assertHasTableActionErrors(['attribute_id']);

        $this->assertSame(1, $item->variantMappings()->count());
    }

    public function test_variant_mapping_can_be_edited_without_changing_its_attribute(): void
    {
        $item = Item::factory()->create();
        $attribute = ItemAttribute::factory()->create();
        $value = $attribute->values()->create([
            'value' => 'Red',
            'code' => 'RD',
        ]);
        $otherValue = $attribute->values()->create([
            'value' => 'Blue',
            'code' => 'BL',
        ]);

        $mapping = ItemVariantMapping::create([
            'item_id' => $item->getKey(),
            'attribute_id' => $attribute->getKey(),
            'attribute_value_id' => $value->getKey(),
        ]);

        Livewire::test(VariantMappingsRelationManager::class, [
            'ownerRecord' => $item,
            'pageClass' => EditItem::class,
        ])
            ->callTableAction(EditAction::class, $mapping, data: [
                'attribute_id' => $attribute->getKey(),
                'attribute_value_id' => $otherValue->getKey(),
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame($otherValue->getKey(), $mapping->fresh()->attribute_value_id);
    }
}
