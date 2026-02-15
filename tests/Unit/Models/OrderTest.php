<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\Organization;
use App\Models\VendorProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
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

    public function test_order_belongs_to_proposal(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $order = Order::factory()->for($proposal)->create(['organization_id' => $this->organization->id]);

        $this->assertInstanceOf(VendorProposal::class, $order->proposal);
        $this->assertEquals($proposal->id, $order->proposal->id);
    }

    public function test_order_vendor_proposal_alias_returns_same_relation(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $order = Order::factory()->for($proposal)->create(['organization_id' => $this->organization->id]);

        $this->assertEquals($order->proposal->id, $order->vendorProposal->id);
    }

    public function test_price_estimated_casts_to_decimal(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'price_estimated' => 12.5,
        ]);

        $fresh = $order->fresh();
        $this->assertEquals('12.50', $fresh->price_estimated);
    }

    public function test_price_final_casts_to_decimal(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'price_final' => 15.0,
        ]);

        $fresh = $order->fresh();
        $this->assertEquals('15.00', $fresh->price_final);
    }

    public function test_audit_log_casts_to_array(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $log = [['at' => now()->toIso8601String(), 'by' => 'U123', 'changes' => ['created' => true]]];
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'audit_log' => $log,
        ]);

        $this->assertIsArray($order->fresh()->audit_log);
        $this->assertEquals('U123', $order->fresh()->audit_log[0]['by']);
    }

    public function test_order_uses_belongs_to_organization(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $order = Order::factory()->for($proposal)->create(['organization_id' => $this->organization->id]);

        $this->assertEquals($this->organization->id, $order->organization_id);
    }

    public function test_factory_creates_valid_order(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $order = Order::factory()->for($proposal)->create(['organization_id' => $this->organization->id]);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertNotNull($order->description);
        $this->assertNotNull($order->provider_user_id);
    }

    public function test_fillable_attributes_are_correct(): void
    {
        $order = new Order;
        $fillable = $order->getFillable();

        $this->assertContains('organization_id', $fillable);
        $this->assertContains('vendor_proposal_id', $fillable);
        $this->assertContains('provider_user_id', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('price_estimated', $fillable);
        $this->assertContains('price_final', $fillable);
        $this->assertContains('notes', $fillable);
        $this->assertContains('audit_log', $fillable);
    }
}
