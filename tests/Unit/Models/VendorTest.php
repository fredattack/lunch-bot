<?php

namespace Tests\Unit\Models;

use App\Models\Organization;
use App\Models\Vendor;
use App\Models\VendorProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_vendor_has_many_proposals(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        VendorProposal::factory()->for($this->organization)->for($vendor)->create();
        VendorProposal::factory()->for($this->organization)->for($vendor)->create();

        $this->assertCount(2, $vendor->proposals);
    }

    public function test_fulfillment_types_casts_to_array(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create([
            'fulfillment_types' => ['pickup', 'delivery'],
        ]);

        $this->assertIsArray($vendor->fresh()->fulfillment_types);
        $this->assertContains('pickup', $vendor->fresh()->fulfillment_types);
        $this->assertContains('delivery', $vendor->fresh()->fulfillment_types);
    }

    public function test_default_fulfillment_types_is_pickup(): void
    {
        $vendor = new Vendor;

        $this->assertEquals(['pickup'], json_decode($vendor->getAttributes()['fulfillment_types'], true));
    }

    public function test_allow_individual_order_casts_to_boolean(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create([
            'allow_individual_order' => true,
        ]);

        $this->assertTrue($vendor->fresh()->allow_individual_order);
    }

    public function test_active_casts_to_boolean(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['active' => true]);

        $this->assertTrue($vendor->fresh()->active);
    }

    public function test_vendor_uses_belongs_to_organization(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();

        $this->assertEquals($this->organization->id, $vendor->organization_id);
    }

    public function test_register_media_collections_defines_logo_and_menu(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();

        $collections = collect($vendor->getRegisteredMediaCollections());
        $names = $collections->pluck('name')->toArray();

        $this->assertContains('logo', $names);
        $this->assertContains('menu', $names);
    }

    public function test_get_logo_thumb_url_returns_null_when_no_logo(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();

        $this->assertNull($vendor->getLogoThumbUrl());
    }

    public function test_factory_creates_valid_vendor(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id]);
        $this->assertNotNull($vendor->name);
        $this->assertTrue($vendor->active);
    }
}
