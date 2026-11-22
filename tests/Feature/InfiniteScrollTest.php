<?php

namespace Tests\Feature;

use App\Filament\Resources\Items\Pages\ListItems;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InfiniteScrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_items_list_loads_records_in_batches(): void
    {
        Item::factory()->count(60)->create();

        $component = Livewire::test(ListItems::class)
            ->assertOk()
            ->assertSet('infinitePerPage', 50)
            ->assertSet('page', 1);

        $this->assertCount(50, $component->instance()->getTableRecords());

        $component->call('loadMore');

        $this->assertCount(60, $component->instance()->getTableRecords());
        $this->assertTrue($component->instance()->infiniteEnded);
    }
}
