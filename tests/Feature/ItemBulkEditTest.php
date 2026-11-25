<?php

namespace Tests\Feature;

use App\Filament\Resources\Items\Pages\ListItems;
use App\Models\Brand;
use App\Models\Division;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ItemBulkEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_selected_items_master_data_can_be_edited_in_bulk(): void
    {
        $first = Item::factory()->create();
        $second = Item::factory()->create();
        $untouched = Item::factory()->create();

        $type = ItemType::factory()->create();
        $category = ItemCategory::factory()->create();
        $brand = Brand::factory()->create();
        $division = Division::factory()->create();

        Livewire::test(ListItems::class)
            ->callTableBulkAction('bulkEditMasterData', [$first, $second], [
                'change_item_type' => true,
                'item_type_id' => $type->getKey(),
                'change_category' => true,
                'category_id' => $category->getKey(),
                'change_brand' => true,
                'brand_id' => $brand->getKey(),
                'change_division' => true,
                'division_id' => $division->getKey(),
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('2 barang diperbarui.');

        foreach ([$first, $second] as $item) {
            $item->refresh();

            $this->assertSame($type->getKey(), $item->item_type_id);
            $this->assertSame($category->getKey(), $item->category_id);
            $this->assertSame($brand->getKey(), $item->brand_id);
            $this->assertSame($division->getKey(), $item->division_id);
        }

        $untouched->refresh();

        $this->assertNotSame($type->getKey(), $untouched->item_type_id);
        $this->assertNull($untouched->category_id);
        $this->assertNull($untouched->brand_id);
        $this->assertNull($untouched->division_id);
    }

    public function test_unchecked_master_data_is_left_unchanged(): void
    {
        $category = ItemCategory::factory()->create();
        $item = Item::factory()->withCategory($category)->create();

        $division = Division::factory()->create();

        Livewire::test(ListItems::class)
            ->callTableBulkAction('bulkEditMasterData', [$item], [
                'change_division' => true,
                'division_id' => $division->getKey(),
            ])
            ->assertHasNoFormErrors();

        $item->refresh();

        $this->assertSame($category->getKey(), $item->category_id);
        $this->assertSame($division->getKey(), $item->division_id);
    }

    public function test_clearing_a_master_data_selection_removes_the_value(): void
    {
        $item = Item::factory()
            ->withCategory()
            ->withBrand()
            ->withDivision()
            ->create();

        Livewire::test(ListItems::class)
            ->callTableBulkAction('bulkEditMasterData', [$item], [
                'change_category' => true,
                'category_id' => null,
                'change_brand' => true,
                'brand_id' => null,
                'change_division' => true,
                'division_id' => null,
            ])
            ->assertHasNoFormErrors();

        $item->refresh();

        $this->assertNull($item->category_id);
        $this->assertNull($item->brand_id);
        $this->assertNull($item->division_id);
    }

    public function test_item_type_is_required_when_changing_the_type(): void
    {
        $item = Item::factory()->create();
        $originalTypeId = $item->item_type_id;

        Livewire::test(ListItems::class)
            ->callTableBulkAction('bulkEditMasterData', [$item], [
                'change_item_type' => true,
                'item_type_id' => null,
            ])
            ->assertHasFormErrors(['item_type_id' => 'required']);

        $this->assertSame($originalTypeId, $item->refresh()->item_type_id);
    }
}
