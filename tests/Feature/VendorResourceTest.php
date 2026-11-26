<?php

namespace Tests\Feature;

use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Models\Vendor;
use Filament\Actions\DeleteAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VendorResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_vendor_list_loads_records_in_batches(): void
    {
        Vendor::factory()->count(60)->create();

        $component = Livewire::test(ListVendors::class)
            ->assertOk()
            ->assertSet('infinitePerPage', 50);

        $this->assertCount(50, $component->instance()->getTableRecords());

        $component->call('loadMore');

        $this->assertCount(60, $component->instance()->getTableRecords());
        $this->assertTrue($component->instance()->infiniteEnded);
    }

    public function test_vendors_can_be_created_with_standard_details(): void
    {
        $data = [
            'code' => 'VND-001',
            'name' => 'Helmet Supplies',
            'contact_name' => 'Dika',
            'phone' => '081234567890',
            'email' => 'sales@example.com',
            'address' => 'Jl. Industri 10',
            'npwp' => '12.345.678.9-012.345',
            'note' => 'Material supplier',
            'is_active' => true,
        ];

        Livewire::test(CreateVendor::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vendors', $data);
    }

    public function test_vendor_code_and_name_are_required(): void
    {
        Livewire::test(CreateVendor::class)
            ->fillForm(['code' => '', 'name' => ''])
            ->call('create')
            ->assertHasFormErrors(['code' => 'required', 'name' => 'required']);

        $this->assertDatabaseCount('vendors', 0);
    }

    public function test_duplicate_vendor_codes_are_rejected(): void
    {
        $vendor = Vendor::factory()->create();

        Livewire::test(CreateVendor::class)
            ->fillForm(['code' => $vendor->code, 'name' => 'Another supplier'])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique']);

        $this->assertDatabaseCount('vendors', 1);
    }

    public function test_invalid_vendor_email_is_rejected(): void
    {
        Livewire::test(CreateVendor::class)
            ->fillForm(['code' => 'VND-001', 'name' => 'Helmet Supplies', 'email' => 'invalid-email'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'email']);

        $this->assertDatabaseCount('vendors', 0);
    }

    public function test_vendor_details_can_be_edited_and_deactivated_without_changing_the_code(): void
    {
        $vendor = Vendor::factory()->create();

        Livewire::test(EditVendor::class, ['record' => $vendor->getKey()])
            ->fillForm(['name' => 'Updated supplier', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->getKey(),
            'code' => $vendor->code,
            'name' => 'Updated supplier',
            'is_active' => false,
        ]);
    }

    public function test_vendor_list_can_be_filtered_by_active_status(): void
    {
        $active = Vendor::factory()->create();
        $inactive = Vendor::factory()->create(['is_active' => false]);

        Livewire::test(ListVendors::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_unused_vendors_can_be_deleted(): void
    {
        $vendor = Vendor::factory()->create();

        Livewire::test(ListVendors::class)
            ->assertTableActionVisible('delete', $vendor)
            ->callTableAction(DeleteAction::class, $vendor);

        $this->assertModelMissing($vendor);
    }

    public function test_vendor_delete_action_is_hidden_when_transactions_exist(): void
    {
        $vendor = Vendor::factory()->create();
        InventoryMovement::factory()->for($vendor)->create();

        Livewire::test(ListVendors::class)
            ->assertTableActionHidden('delete', $vendor);

        $this->assertModelExists($vendor);
    }

    public function test_database_prevents_deleting_vendors_used_by_transactions(): void
    {
        $vendor = Vendor::factory()->create();
        $movement = InventoryMovement::factory()->for($vendor)->create();

        $this->expectException(QueryException::class);

        try {
            $vendor->delete();
        } finally {
            $this->assertModelExists($vendor);
            $this->assertTrue($movement->fresh()->vendor->is($vendor));
        }
    }
}
