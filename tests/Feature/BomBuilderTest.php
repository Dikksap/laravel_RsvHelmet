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
        $parent = Item::factory()->create();
        $component = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $component->getKey(),
            'quantity' => 2,
        ]);

        $this->get('/admin/bom-builder')->assertOk();
        $this->get("/admin/bom-builder?item={$parent->getKey()}")->assertOk();
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

        $rows = array_values($testable->get('data.components'));

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

    public function test_manage_action_loads_composition_into_the_form(): void
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

        $rows = array_values($testable->get('data.components'));

        $this->assertCount(1, $rows);
        $this->assertSame($component->getKey(), $rows[0]['component_item_id']);
        $this->assertSame(3.0, (float) $rows[0]['quantity']);
    }

    public function test_using_a_composition_as_template_fills_the_form_without_saving(): void
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

        $rows = array_values($testable->get('data.components'));

        $this->assertCount(1, $rows);
        $this->assertSame($component->getKey(), $rows[0]['component_item_id']);
        $this->assertSame(2.5, (float) $rows[0]['quantity']);
        $this->assertSame('Bahan utama', $rows[0]['note']);
        $this->assertFalse((bool) $rows[0]['is_active']);

        $this->assertSame(0, $target->components()->count());
        $this->assertSame(1, ItemComponent::query()->count());
    }

    public function test_composition_created_from_template_is_saved_after_form_submission(): void
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

        $rows = array_values($testable->get('data.components'));
        $rows[0]['quantity'] = 5;

        $testable
            ->set('data.components', $rows)
            ->call('save')
            ->assertHasNoFormErrors();

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

    public function test_components_can_be_added_in_bulk_through_bom_builder(): void
    {
        $parent = Item::factory()->create();
        $first = Item::factory()->create();
        $second = Item::factory()->create();

        Livewire::test(BomBuilder::class)
            ->fillForm([
                'parent_item_id' => $parent->getKey(),
                'components' => [
                    ['component_item_id' => $first->getKey(), 'quantity' => 1, 'is_active' => true],
                    ['component_item_id' => $second->getKey(), 'quantity' => 2.5, 'is_active' => true],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $components = $parent->components()->get()->keyBy('component_item_id');

        $this->assertCount(2, $components);
        $this->assertSame(1.0, (float) $components[$first->getKey()]->quantity);
        $this->assertSame(2.5, (float) $components[$second->getKey()]->quantity);
    }

    public function test_saving_updates_existing_rows_and_removes_omitted_rows(): void
    {
        $parent = Item::factory()->create();
        $kept = Item::factory()->create();
        $removed = Item::factory()->create();
        $added = Item::factory()->create();

        $keptComponent = ItemComponent::factory()->create([
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $kept->getKey(),
            'quantity' => 1,
        ]);
        ItemComponent::factory()->create([
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $removed->getKey(),
            'quantity' => 1,
        ]);

        Livewire::test(BomBuilder::class)
            ->fillForm([
                'parent_item_id' => $parent->getKey(),
                'components' => [
                    ['component_item_id' => $kept->getKey(), 'quantity' => 7, 'is_active' => false],
                    ['component_item_id' => $added->getKey(), 'quantity' => 3, 'is_active' => true],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(7.0, (float) $keptComponent->fresh()->quantity);
        $this->assertFalse($keptComponent->fresh()->is_active);
        $this->assertDatabaseMissing('item_components', ['component_item_id' => $removed->getKey()]);
        $this->assertDatabaseHas('item_components', [
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $added->getKey(),
        ]);
    }

    public function test_duplicate_component_rows_are_rejected(): void
    {
        $parent = Item::factory()->create();
        $component = Item::factory()->create();

        Livewire::test(BomBuilder::class)
            ->fillForm([
                'parent_item_id' => $parent->getKey(),
                'components' => [
                    ['component_item_id' => $component->getKey(), 'quantity' => 1, 'is_active' => true],
                    ['component_item_id' => $component->getKey(), 'quantity' => 2, 'is_active' => true],
                ],
            ])
            ->call('save')
            ->assertHasErrors();

        $this->assertSame(0, ItemComponent::query()->count());
    }

    public function test_item_cannot_be_its_own_component(): void
    {
        $parent = Item::factory()->create();

        Livewire::test(BomBuilder::class)
            ->fillForm([
                'parent_item_id' => $parent->getKey(),
                'components' => [
                    ['component_item_id' => $parent->getKey(), 'quantity' => 1, 'is_active' => true],
                ],
            ])
            ->call('save')
            ->assertHasErrors();

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
            ->fillForm([
                'parent_item_id' => $second->getKey(),
                'components' => [
                    ['component_item_id' => $first->getKey(), 'quantity' => 1, 'is_active' => true],
                ],
            ])
            ->call('save')
            ->assertHasErrors();

        $this->assertSame(1, ItemComponent::query()->count());
    }
}
