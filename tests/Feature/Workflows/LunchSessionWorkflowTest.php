<?php

namespace Tests\Feature\Workflows;

use App\Actions\LunchSession\CloseLunchSession;
use App\Actions\LunchSession\CreateLunchSession;
use App\Actions\LunchSession\LockExpiredSessions;
use App\Actions\Order\CreateOrder;
use App\Actions\Order\UpdateOrder;
use App\Actions\VendorProposal\AssignRole;
use App\Actions\VendorProposal\DelegateRole;
use App\Actions\VendorProposal\ProposeVendor;
use App\Enums\FulfillmentType;
use App\Enums\LunchSessionStatus;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Organization;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LunchSessionWorkflowTest extends TestCase
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
        Carbon::setTestNow();
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_complete_lunch_workflow_from_session_to_close(): void
    {
        // 1. Create session
        $date = '2025-06-15';
        $channelId = 'C_LUNCH';
        $deadline = Carbon::parse("{$date} 11:30:00", 'Europe/Paris');
        Carbon::setTestNow(Carbon::parse("{$date} 10:00:00", 'Europe/Paris'));

        $session = app(CreateLunchSession::class)->handle($date, $channelId, $deadline);

        $this->assertInstanceOf(LunchSession::class, $session);
        $this->assertEquals(LunchSessionStatus::Open, $session->status);

        // 2. Create vendors
        $vendorPickup = Vendor::factory()->for($this->organization)->create([
            'name' => 'Sushi Palace',
            'active' => true,
        ]);
        $vendorDelivery = Vendor::factory()->for($this->organization)->create([
            'name' => 'Pizza Roma',
            'active' => true,
        ]);

        // 3. Propose vendor (pickup)
        $proposal1 = app(ProposeVendor::class)->handle(
            $session,
            $vendorPickup,
            FulfillmentType::Pickup,
            'U_ALICE',
            '11:30'
        );
        $this->assertEquals(ProposalStatus::Open, $proposal1->status);
        $this->assertEquals('U_ALICE', $proposal1->runner_user_id);

        // 4. Propose vendor (delivery)
        $proposal2 = app(ProposeVendor::class)->handle(
            $session,
            $vendorDelivery,
            FulfillmentType::Delivery,
            'U_BOB',
            '12:00'
        );
        $this->assertEquals(ProposalStatus::Open, $proposal2->status);
        $this->assertEquals('U_BOB', $proposal2->orderer_user_id);

        // 5. Create orders
        $order1 = app(CreateOrder::class)->handle($proposal1, 'U_ALICE', [
            'description' => 'California Roll',
            'price_estimated' => 12.50,
        ]);
        $this->assertNotNull($order1->id);

        $order2 = app(CreateOrder::class)->handle($proposal1, 'U_CHARLIE', [
            'description' => 'Dragon Roll',
            'price_estimated' => 15.00,
        ]);

        $order3 = app(CreateOrder::class)->handle($proposal2, 'U_DAVE', [
            'description' => 'Margherita Pizza',
            'price_estimated' => 10.00,
        ]);

        // 6. Update an order
        $updatedOrder = app(UpdateOrder::class)->handle($order1, [
            'description' => 'Spicy California Roll',
            'price_estimated' => 13.00,
        ], 'U_ALICE');
        $this->assertEquals('Spicy California Roll', $updatedOrder->description);

        // 7. Assign orderer on proposal1
        $assigned = app(AssignRole::class)->handle($proposal1, 'orderer', 'U_EVE');
        $this->assertTrue($assigned);
        $this->assertEquals('U_EVE', $proposal1->fresh()->orderer_user_id);
        $this->assertEquals(ProposalStatus::Ordering, $proposal1->fresh()->status);

        // 8. Delegate runner on proposal1
        $delegated = app(DelegateRole::class)->handle($proposal1->fresh(), 'runner', 'U_ALICE', 'U_FRANK');
        $this->assertTrue($delegated);
        $this->assertEquals('U_FRANK', $proposal1->fresh()->runner_user_id);

        // 9. Lock expired sessions
        Carbon::setTestNow(Carbon::parse("{$date} 12:30:00", 'Europe/Paris'));
        $locked = app(LockExpiredSessions::class)->handle('Europe/Paris');
        $this->assertCount(1, $locked);
        $this->assertEquals(LunchSessionStatus::Locked, $session->fresh()->status);

        // 10. Close session
        app(CloseLunchSession::class)->handle($session->fresh());
        $this->assertEquals(LunchSessionStatus::Closed, $session->fresh()->status);

        // Verify proposal statuses
        $this->assertEquals(ProposalStatus::Closed, $proposal1->fresh()->status);
        $this->assertEquals(ProposalStatus::Closed, $proposal2->fresh()->status);

        // Verify orders still intact
        $this->assertDatabaseCount('orders', 3);
        $this->assertDatabaseHas('orders', [
            'id' => $order1->id,
            'description' => 'Spicy California Roll',
        ]);

        // Verify audit log exists on updated order
        $auditLog = $order1->fresh()->audit_log;
        $this->assertIsArray($auditLog);
        $this->assertGreaterThanOrEqual(1, count($auditLog));
    }
}
