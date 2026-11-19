<?php

namespace Tests\Feature;

use App\Filament\Pages\BomBuilder;
use App\Models\Item;
use App\Models\ItemComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BomBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_bom_builder_page_renders(): void
    {
        $parent = Item::factory()->create(['item_name' => 'Meja Belajar']);
        $component = Item::factory()->create(['item_name' => 'Rangka Kaki']);
        $nested = Item::factory()->create(['item_name' => 'Besi Hollow']);

        ItemComponent::factory()->create([
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $component->getKey(),
            'quantity' => 2,
        ]);

        ItemComponent::factory()->create([
            'parent_item_id' => $component->getKey(),
            'component_item_id' => $nested->getKey(),
            'quantity' => 3,
        ]);

        $this->get('/admin/bom-builder')
            ->assertOk()
            ->assertSee('Struktur BOM')
            ->assertSee('Daftar belanja')
            ->assertSee('Pustaka komponen');

        $this->get("/admin/bom-builder?item={$parent->getKey()}")
            ->assertOk()
            ->assertSee('Meja Belajar')
            ->assertSee('Rangka Kaki')
            ->assertSee('Besi Hollow')
            ->assertSee('Simpan komposisi');
    }

    public function test_selecting_a_parent_item_loads_its_existing_components(): void
    {
        $parent = Item::factory()->create();
        $component = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $component->getKey(),
            'quantity' => 4,
        ]);

        $testable = Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $parent->getKey());

        $rows = $testable->get('groups')[$parent->getKey()];

        $this->assertCount(1, $rows);
        $this->assertSame($component->getKey(), $rows[0]['component_item_id']);
        $this->assertSame(4.0, (float) $rows[0]['quantity']);
    }

    public function test_bom_builder_table_only_lists_items_with_composition(): void
    {
        $parent = Item::factory()->create();
        $component = Item::factory()->create();
        $itemWithoutComposition = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $component->getKey(),
        ]);

        Livewire::test(BomBuilder::class)
            ->assertCanSeeTableRecords([$parent])
            ->assertCanNotSeeTableRecords([$component, $itemWithoutComposition]);
    }

    public function test_manage_action_loads_composition_into_the_builder(): void
    {
        $parent = Item::factory()->create();
        $component = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $component->getKey(),
            'quantity' => 3,
        ]);

        $testable = Livewire::test(BomBuilder::class)
            ->callTableAction('manage', $parent)
            ->assertSet('data.parent_item_id', $parent->getKey());

        $rows = $testable->get('groups')[$parent->getKey()];

        $this->assertCount(1, $rows);
        $this->assertSame($component->getKey(), $rows[0]['component_item_id']);
        $this->assertSame(3.0, (float) $rows[0]['quantity']);
    }

    public function test_nested_composition_levels_are_loaded_and_editable(): void
    {
        $root = Item::factory()->create();
        $subAssembly = Item::factory()->create();
        $leaf = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $subAssembly->getKey(),
            'quantity' => 2,
        ]);

        ItemComponent::factory()->create([
            'parent_item_id' => $subAssembly->getKey(),
            'component_item_id' => $leaf->getKey(),
            'quantity' => 3,
        ]);

        $testable = Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $root->getKey());

        $groups = $testable->get('groups');

        $this->assertArrayHasKey($subAssembly->getKey(), $groups);
        $this->assertSame($leaf->getKey(), $groups[$subAssembly->getKey()][0]['component_item_id']);

        $testable
            ->call('updateQuantity', $subAssembly->getKey(), 0, 7)
            ->call('save');

        $this->assertDatabaseHas('item_components', [
            'parent_item_id' => $subAssembly->getKey(),
            'component_item_id' => $leaf->getKey(),
            'quantity' => 7,
        ]);
    }

    public function test_components_can_be_added_at_root_and_nested_level(): void
    {
        $root = Item::factory()->create();
        $subAssembly = Item::factory()->create();
        $direct = Item::factory()->create();
        $nested = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $subAssembly->getKey(),
        ]);

        Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $root->getKey())
            ->call('addComponent', $root->getKey(), $direct->getKey())
            ->call('addComponent', $subAssembly->getKey(), $nested->getKey())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('item_components', [
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $direct->getKey(),
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('item_components', [
            'parent_item_id' => $subAssembly->getKey(),
            'component_item_id' => $nested->getKey(),
        ]);
    }

    public function test_saving_updates_existing_rows_and_removes_omitted_rows(): void
    {
        $parent = Item::factory()->create();
        $kept = Item::factory()->create();
        $removed = Item::factory()->create();

        $keptComponent = ItemComponent::factory()->create([
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $kept->getKey(),
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        ItemComponent::factory()->create([
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $removed->getKey(),
            'quantity' => 1,
            'sort_order' => 1,
        ]);

        Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $parent->getKey())
            ->call('updateQuantity', $parent->getKey(), 0, 7)
            ->call('toggleActive', $parent->getKey(), 0)
            ->call('removeRow', $parent->getKey(), 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(7.0, (float) $keptComponent->fresh()->quantity);
        $this->assertFalse($keptComponent->fresh()->is_active);
        $this->assertDatabaseMissing('item_components', ['component_item_id' => $removed->getKey()]);
    }

    public function test_dirty_tracking_only_persists_changed_groups(): void
    {
        $root = Item::factory()->create();
        $subAssembly = Item::factory()->create();
        $leaf = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $subAssembly->getKey(),
        ]);

        $leafComponent = ItemComponent::factory()->create([
            'parent_item_id' => $subAssembly->getKey(),
            'component_item_id' => $leaf->getKey(),
        ]);

        $unchangedTimestamp = $leafComponent->updated_at;

        $this->travel(5)->minutes();

        Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $root->getKey())
            ->call('updateQuantity', $root->getKey(), 0, 4)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($unchangedTimestamp->toDateTimeString(), $leafComponent->fresh()->updated_at->toDateTimeString());
    }

    public function test_duplicate_component_at_nested_level_is_rejected(): void
    {
        $root = Item::factory()->create();
        $subAssembly = Item::factory()->create();
        $leaf = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $subAssembly->getKey(),
        ]);

        ItemComponent::factory()->create([
            'parent_item_id' => $subAssembly->getKey(),
            'component_item_id' => $leaf->getKey(),
        ]);

        Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $root->getKey())
            ->call('addComponent', $subAssembly->getKey(), $leaf->getKey())
            ->assertHasErrors(['groups']);
    }

    public function test_item_cannot_be_its_own_component(): void
    {
        $parent = Item::factory()->create();

        Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $parent->getKey())
            ->call('addComponent', $parent->getKey(), $parent->getKey())
            ->assertHasErrors(['groups']);

        $this->assertSame(0, ItemComponent::query()->count());
    }

    public function test_cyclic_composition_is_rejected(): void
    {
        $first = Item::factory()->create();
        $second = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $first->getKey(),
            'component_item_id' => $second->getKey(),
        ]);

        Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $second->getKey())
            ->call('addComponent', $second->getKey(), $first->getKey())
            ->assertHasErrors(['groups']);

        $this->assertSame(1, ItemComponent::query()->count());
    }

    public function test_rows_can_be_reordered(): void
    {
        $root = Item::factory()->create();
        $first = Item::factory()->create();
        $second = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $first->getKey(),
            'sort_order' => 0,
        ]);

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $second->getKey(),
            'sort_order' => 1,
        ]);

        Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $root->getKey())
            ->call('moveRow', $root->getKey(), 1, $root->getKey(), 0, 'before')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('item_components', [
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $second->getKey(),
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('item_components', [
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $first->getKey(),
            'sort_order' => 1,
        ]);
    }

    public function test_row_can_be_nested_into_another_assembly(): void
    {
        $root = Item::factory()->create();
        $subAssembly = Item::factory()->create();
        $direct = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $subAssembly->getKey(),
            'sort_order' => 0,
        ]);

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $direct->getKey(),
            'sort_order' => 1,
        ]);

        Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $root->getKey())
            ->call('moveRow', $root->getKey(), 1, $subAssembly->getKey(), 0, 'in')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('item_components', [
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $direct->getKey(),
        ]);

        $this->assertDatabaseHas('item_components', [
            'parent_item_id' => $subAssembly->getKey(),
            'component_item_id' => $direct->getKey(),
        ]);
    }

    public function test_summary_calculates_cost_per_unit_and_production_total(): void
    {
        $root = Item::factory()->create();
        $subAssembly = Item::factory()->create();
        $leaf = Item::factory()->create(['standard_cost' => 1000]);
        $direct = Item::factory()->create(['standard_cost' => 500]);

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $subAssembly->getKey(),
            'quantity' => 2,
        ]);

        ItemComponent::factory()->create([
            'parent_item_id' => $subAssembly->getKey(),
            'component_item_id' => $leaf->getKey(),
            'quantity' => 3,
        ]);

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $direct->getKey(),
            'quantity' => 1,
        ]);

        $testable = Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $root->getKey())
            ->set('productionQty', 4);

        $summary = $testable->instance()->summary();

        $this->assertSame(6500.0, (float) $summary['per_unit']);
        $this->assertSame(26000.0, (float) $summary['total']);
        $this->assertSame(3, $summary['rows']);
        $this->assertSame(2, $summary['leaves']);
    }

    public function test_inactive_components_are_excluded_from_cost(): void
    {
        $root = Item::factory()->create();
        $leaf = Item::factory()->create(['standard_cost' => 1000]);

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $leaf->getKey(),
            'quantity' => 2,
            'is_active' => false,
        ]);

        $testable = Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $root->getKey());

        $this->assertSame(0.0, (float) $testable->instance()->summary()['per_unit']);
    }

    public function test_shopping_list_aggregates_nested_requirements(): void
    {
        $root = Item::factory()->create();
        $subAssembly = Item::factory()->create();
        $leaf = Item::factory()->create(['standard_cost' => 250]);

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $subAssembly->getKey(),
            'quantity' => 2,
        ]);

        ItemComponent::factory()->create([
            'parent_item_id' => $subAssembly->getKey(),
            'component_item_id' => $leaf->getKey(),
            'quantity' => 3,
        ]);

        $testable = Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $root->getKey())
            ->set('productionQty', 2);

        $shopping = $testable->instance()->shoppingList();

        $this->assertCount(1, $shopping);
        $this->assertSame($leaf->getKey(), $shopping[0]['item']->getKey());
        $this->assertSame(12.0, (float) $shopping[0]['quantity']);
        $this->assertSame(3000.0, (float) $shopping[0]['subtotal']);
    }

    public function test_builder_can_export_the_composition_as_csv(): void
    {
        $root = Item::factory()->create(['item_code' => 'MBL-001', 'item_name' => 'Meja Belajar']);
        $leaf = Item::factory()->create(['item_code' => 'MDF-18', 'item_name' => 'Papan MDF']);

        ItemComponent::factory()->create([
            'parent_item_id' => $root->getKey(),
            'component_item_id' => $leaf->getKey(),
            'quantity' => 2,
        ]);

        $testable = Livewire::test(BomBuilder::class)
            ->set('data.parent_item_id', $root->getKey());

        $response = $testable->instance()->exportCsv();

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Komposisi Barang', $csv);
        $this->assertStringContainsString('MBL-001', $csv);
        $this->assertStringContainsString('MDF-18', $csv);
        $this->assertStringContainsString('Daftar belanja', $csv);
    }

    public function test_using_a_composition_as_template_fills_the_builder_without_saving(): void
    {
        $template = Item::factory()->create();
        $component = Item::factory()->create();
        $target = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $template->getKey(),
            'component_item_id' => $component->getKey(),
            'quantity' => 2.5,
            'note' => 'Bahan utama',
            'is_active' => false,
        ]);

        $testable = Livewire::test(BomBuilder::class)
            ->callTableAction('useAsTemplate', $template, data: [
                'source_item_id' => $template->getKey(),
                'target_item_id' => $target->getKey(),
            ])
            ->assertHasNoTableActionErrors()
            ->assertSet('data.parent_item_id', $target->getKey());

        $rows = $testable->get('groups')[$target->getKey()];

        $this->assertCount(1, $rows);
        $this->assertSame($component->getKey(), $rows[0]['component_item_id']);
        $this->assertSame(2.5, (float) $rows[0]['quantity']);
        $this->assertSame('Bahan utama', $rows[0]['note']);
        $this->assertFalse((bool) $rows[0]['is_active']);

        $this->assertSame(0, $target->components()->count());
        $this->assertSame(1, ItemComponent::query()->count());
    }

    public function test_composition_created_from_template_is_saved_after_submission(): void
    {
        $template = Item::factory()->create();
        $component = Item::factory()->create();
        $target = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $template->getKey(),
            'component_item_id' => $component->getKey(),
            'quantity' => 2,
        ]);

        $testable = Livewire::test(BomBuilder::class)
            ->callTableAction('useAsTemplate', $template, data: [
                'source_item_id' => $template->getKey(),
                'target_item_id' => $target->getKey(),
            ])
            ->assertHasNoTableActionErrors();

        $testable
            ->call('updateQuantity', $target->getKey(), 0, 5)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('item_components', [
            'parent_item_id' => $target->getKey(),
            'component_item_id' => $component->getKey(),
            'quantity' => 5,
        ]);
    }

    public function test_template_can_be_applied_through_the_table_header_action(): void
    {
        $template = Item::factory()->create();
        $component = Item::factory()->create();
        $target = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $template->getKey(),
            'component_item_id' => $component->getKey(),
        ]);

        Livewire::test(BomBuilder::class)
            ->callTableAction('createFromTemplate', data: [
                'source_item_id' => $template->getKey(),
                'target_item_id' => $target->getKey(),
            ])
            ->assertHasNoTableActionErrors()
            ->assertSet('data.parent_item_id', $target->getKey());
    }

    public function test_template_target_must_differ_from_the_template_item(): void
    {
        $template = Item::factory()->create();
        $component = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $template->getKey(),
            'component_item_id' => $component->getKey(),
        ]);

        Livewire::test(BomBuilder::class)
            ->callTableAction('useAsTemplate', $template, data: [
                'source_item_id' => $template->getKey(),
                'target_item_id' => $template->getKey(),
            ])
            ->assertHasTableActionErrors(['target_item_id']);
    }
}
