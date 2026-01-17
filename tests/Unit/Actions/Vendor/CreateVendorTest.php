<?php

namespace Tests\Unit\Actions\Vendor;

use App\Actions\Vendor\CreateVendor;
use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateVendorTest extends TestCase
{
    use RefreshDatabase;

    private CreateVendor $action;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateVendor;
        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_creates_vendor_with_all_fields(): void
    {
        $name = 'Pizza Palace';
        $urlMenu = 'https://pizzapalace.com/menu';
        $notes = 'Best pizza in town';
        $userId = 'U12345678';

        $vendor = $this->action->handle($name, $urlMenu, $notes, $userId);

        $this->assertInstanceOf(Vendor::class, $vendor);
        $this->assertEquals($name, $vendor->name);
        $this->assertEquals($urlMenu, $vendor->url_menu);
        $this->assertEquals($notes, $vendor->notes);
        $this->assertEquals($userId, $vendor->created_by_provider_user_id);
        $this->assertTrue($vendor->active);
        $this->assertEquals($this->organization->id, $vendor->organization_id);
    }

    public function test_creates_vendor_with_minimal_fields(): void
    {
        $vendor = $this->action->handle('Burger Shack', null, null, 'U12345678');

        $this->assertInstanceOf(Vendor::class, $vendor);
        $this->assertEquals('Burger Shack', $vendor->name);
        $this->assertNull($vendor->url_menu);
        $this->assertNull($vendor->notes);
    }

    public function test_sets_created_by_user_id(): void
    {
        $userId = 'U_CREATOR_123';

        $vendor = $this->action->handle('Taco Town', null, null, $userId);

        $this->assertEquals($userId, $vendor->created_by_provider_user_id);
    }

    public function test_vendor_is_active_by_default(): void
    {
        $vendor = $this->action->handle('Sushi Express', null, null, 'U12345678');

        $this->assertTrue($vendor->active);
    }

    public function test_vendor_belongs_to_current_organization(): void
    {
        $vendor = $this->action->handle('Thai Delight', null, null, 'U12345678');

        $this->assertEquals($this->organization->id, $vendor->organization_id);
        $this->assertTrue($vendor->organization->is($this->organization));
    }

    public function test_persists_vendor_to_database(): void
    {
        $vendor = $this->action->handle('Indian Spice', 'https://example.com', 'Curry lovers', 'U12345678');

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'Indian Spice',
            'url_menu' => 'https://example.com',
            'notes' => 'Curry lovers',
            'active' => true,
            'created_by_provider_user_id' => 'U12345678',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_duplicate_vendor_name_throws_exception(): void
    {
        $this->action->handle('Pizza Place', null, null, 'U12345678');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $this->action->handle('Pizza Place', null, null, 'U12345678');
    }

    public function test_creates_vendors_with_different_names(): void
    {
        $vendor1 = $this->action->handle('Pizza Place', null, null, 'U12345678');
        $vendor2 = $this->action->handle('Burger Joint', null, null, 'U12345678');

        $this->assertNotEquals($vendor1->id, $vendor2->id);
        $this->assertDatabaseCount('vendors', 2);
    }
}
