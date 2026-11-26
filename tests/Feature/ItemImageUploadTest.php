<?php

namespace Tests\Feature;

use App\Filament\Resources\Items\Pages\CreateItem;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ItemImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        Storage::fake('public');
    }

    public function test_item_image_is_stored_on_public_disk_when_creating_item(): void
    {
        $type = ItemType::factory()->create();
        $uom = Uom::factory()->create();

        Livewire::test(CreateItem::class)
            ->fillForm([
                'item_code' => 'IMG-0001',
                'item_name' => 'Helmet Uji Gambar',
                'item_type_id' => $type->getKey(),
                'base_uom_id' => $uom->getKey(),
                'image_path' => UploadedFile::fake()->image('helmet.jpg', 400, 400),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = Item::query()->where('item_code', 'IMG-0001')->first();

        $this->assertNotNull($item);
        $this->assertNotNull($item->image_path);
        $this->assertStringStartsWith('items/', $item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
    }
}
