<?php

namespace Tests\Feature;

use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Vendor;
use App\Models\VendorProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org1;

    private Organization $org2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org1 = Organization::factory()->withInstallation()->create(['name' => 'Org One']);
        $this->org2 = Organization::factory()->withInstallation()->create(['name' => 'Org Two']);
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_lunch_sessions_are_isolated_by_organization(): void
    {
        $session1 = LunchSession::factory()->create(['organization_id' => $this->org1->id]);
        $session2 = LunchSession::factory()->create(['organization_id' => $this->org2->id]);

        Organization::setCurrent($this->org1);
        $org1Sessions = LunchSession::all();

        $this->assertCount(1, $org1Sessions);
        $this->assertEquals($session1->id, $org1Sessions->first()->id);

        Organization::setCurrent($this->org2);
        $org2Sessions = LunchSession::all();

        $this->assertCount(1, $org2Sessions);
        $this->assertEquals($session2->id, $org2Sessions->first()->id);
    }

    public function test_vendors_are_isolated_by_organization(): void
    {
        $vendor1 = Vendor::factory()->create(['organization_id' => $this->org1->id, 'name' => 'Vendor A']);
        $vendor2 = Vendor::factory()->create(['organization_id' => $this->org2->id, 'name' => 'Vendor B']);

        Organization::setCurrent($this->org1);

        $this->assertCount(1, Vendor::all());
        $this->assertEquals('Vendor A', Vendor::first()->name);

        Organization::setCurrent($this->org2);

        $this->assertCount(1, Vendor::all());
        $this->assertEquals('Vendor B', Vendor::first()->name);
    }

    public function test_vendor_proposals_are_isolated_by_organization(): void
    {
        $session1 = LunchSession::factory()->create(['organization_id' => $this->org1->id]);
        $session2 = LunchSession::factory()->create(['organization_id' => $this->org2->id]);

        $vendor1 = Vendor::factory()->create(['organization_id' => $this->org1->id]);
        $vendor2 = Vendor::factory()->create(['organization_id' => $this->org2->id]);

        $proposal1 = VendorProposal::factory()->create([
            'organization_id' => $this->org1->id,
            'lunch_session_id' => $session1->id,
            'vendor_id' => $vendor1->id,
        ]);

        $proposal2 = VendorProposal::factory()->create([
            'organization_id' => $this->org2->id,
            'lunch_session_id' => $session2->id,
            'vendor_id' => $vendor2->id,
        ]);

        Organization::setCurrent($this->org1);
        $this->assertCount(1, VendorProposal::all());
        $this->assertEquals($proposal1->id, VendorProposal::first()->id);

        Organization::setCurrent($this->org2);
        $this->assertCount(1, VendorProposal::all());
        $this->assertEquals($proposal2->id, VendorProposal::first()->id);
    }

    public function test_orders_are_isolated_by_organization(): void
    {
        $session1 = LunchSession::factory()->create(['organization_id' => $this->org1->id]);
        $vendor1 = Vendor::factory()->create(['organization_id' => $this->org1->id]);
        $proposal1 = VendorProposal::factory()->create([
            'organization_id' => $this->org1->id,
            'lunch_session_id' => $session1->id,
            'vendor_id' => $vendor1->id,
        ]);

        $session2 = LunchSession::factory()->create(['organization_id' => $this->org2->id]);
        $vendor2 = Vendor::factory()->create(['organization_id' => $this->org2->id]);
        $proposal2 = VendorProposal::factory()->create([
            'organization_id' => $this->org2->id,
            'lunch_session_id' => $session2->id,
            'vendor_id' => $vendor2->id,
        ]);

        $order1 = Order::factory()->create([
            'organization_id' => $this->org1->id,
            'vendor_proposal_id' => $proposal1->id,
        ]);

        $order2 = Order::factory()->create([
            'organization_id' => $this->org2->id,
            'vendor_proposal_id' => $proposal2->id,
        ]);

        Organization::setCurrent($this->org1);
        $this->assertCount(1, Order::all());
        $this->assertEquals($order1->id, Order::first()->id);

        Organization::setCurrent($this->org2);
        $this->assertCount(1, Order::all());
        $this->assertEquals($order2->id, Order::first()->id);
    }

    public function test_same_vendor_name_allowed_in_different_organizations(): void
    {
        Vendor::factory()->create([
            'organization_id' => $this->org1->id,
            'name' => 'Pizza Place',
        ]);

        Vendor::factory()->create([
            'organization_id' => $this->org2->id,
            'name' => 'Pizza Place',
        ]);

        $this->assertDatabaseCount('vendors', 2);
    }

    public function test_relations_work_with_organization_scope(): void
    {
        $session = LunchSession::factory()->create(['organization_id' => $this->org1->id]);
        $vendor = Vendor::factory()->create(['organization_id' => $this->org1->id]);
        $proposal = VendorProposal::factory()->create([
            'organization_id' => $this->org1->id,
            'lunch_session_id' => $session->id,
            'vendor_id' => $vendor->id,
        ]);
        Order::factory()->count(3)->create([
            'organization_id' => $this->org1->id,
            'vendor_proposal_id' => $proposal->id,
        ]);

        Organization::setCurrent($this->org1);

        $session->refresh();
        $this->assertCount(1, $session->proposals);
        $this->assertCount(3, $session->proposals->first()->orders);
    }
}
