<?php

namespace Tests\Unit\Actions\Vendor;

use App\Actions\Vendor\UpdateVendor;
use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateVendorTest extends TestCase
{
    use RefreshDatabase;

    private UpdateVendor $action;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateVendor;
        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_updates_vendor_name(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['name' => 'Old Name']);

        $result = $this->action->handle($vendor, ['name' => 'New Name']);

        $this->assertEquals('New Name', $result->name);
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'New Name',
        ]);
    }

    public function test_updates_vendor_url_menu(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['url_menu' => 'https://old.com']);

        $result = $this->action->handle($vendor, ['url_menu' => 'https://new.com']);

        $this->assertEquals('https://new.com', $result->url_menu);
    }

    public function test_updates_vendor_notes(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['notes' => 'Old notes']);

        $result = $this->action->handle($vendor, ['notes' => 'New notes']);

        $this->assertEquals('New notes', $result->notes);
    }

    public function test_can_deactivate_vendor(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['active' => true]);

        $result = $this->action->handle($vendor, ['active' => false]);

        $this->assertFalse($result->active);
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'active' => false,
        ]);
    }

    public function test_can_reactivate_vendor(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->inactive()->create();

        $result = $this->action->handle($vendor, ['active' => true]);

        $this->assertTrue($result->active);
    }

    public function test_partial_update_preserves_other_fields(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create([
            'name' => 'Original Name',
            'url_menu' => 'https://original.com',
            'notes' => 'Original notes',
            'active' => true,
        ]);

        $result = $this->action->handle($vendor, ['name' => 'Updated Name']);

        $this->assertEquals('Updated Name', $result->name);
        $this->assertEquals('https://original.com', $result->url_menu);
        $this->assertEquals('Original notes', $result->notes);
        $this->assertTrue($result->active);
    }

    public function test_can_set_url_menu_to_null(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['url_menu' => 'https://example.com']);

        $result = $this->action->handle($vendor, ['url_menu' => null]);

        $this->assertNull($result->url_menu);
    }

    public function test_can_set_notes_to_null(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['notes' => 'Some notes']);

        $result = $this->action->handle($vendor, ['notes' => null]);

        $this->assertNull($result->notes);
    }

    public function test_returns_the_vendor_instance(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();

        $result = $this->action->handle($vendor, ['name' => 'Test']);

        $this->assertInstanceOf(Vendor::class, $result);
        $this->assertEquals($vendor->id, $result->id);
    }

    public function test_updates_multiple_fields_at_once(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create([
            'name' => 'Old',
            'url_menu' => 'https://old.com',
            'notes' => 'Old notes',
        ]);

        $result = $this->action->handle($vendor, [
            'name' => 'New',
            'url_menu' => 'https://new.com',
            'notes' => 'New notes',
        ]);

        $this->assertEquals('New', $result->name);
        $this->assertEquals('https://new.com', $result->url_menu);
        $this->assertEquals('New notes', $result->notes);
    }

    public function test_empty_data_array_does_not_modify_vendor(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create([
            'name' => 'Original',
            'url_menu' => 'https://original.com',
        ]);
        $originalUpdatedAt = $vendor->updated_at;

        $result = $this->action->handle($vendor, []);

        $this->assertEquals('Original', $result->name);
        $this->assertEquals('https://original.com', $result->url_menu);
    }
}
