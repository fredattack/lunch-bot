<?php

namespace Tests\Unit\Actions\Lunch;

use App\Actions\Lunch\UpdateOrder;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\VendorProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateOrderTest extends TestCase
{
    use RefreshDatabase;

    private UpdateOrder $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateOrder;
    }

    public function test_updates_order_description(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'description' => 'Old description',
        ]);
        $actorId = 'U_ACTOR';

        $result = $this->action->handle($order, ['description' => 'New description'], $actorId);

        $this->assertEquals('New description', $result->description);
    }

    public function test_appends_audit_log_entry_on_change(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'description' => 'Original',
            'audit_log' => [['at' => now()->toIso8601String(), 'by' => 'U_CREATOR', 'changes' => ['created' => true]]],
        ]);
        $actorId = 'U_UPDATER';

        $this->action->handle($order, ['description' => 'Updated'], $actorId);

        $auditLog = $order->fresh()->audit_log;
        $this->assertCount(2, $auditLog);
        $this->assertEquals('U_UPDATER', $auditLog[1]['by']);
        $this->assertArrayHasKey('description', $auditLog[1]['changes']);
        $this->assertEquals('Original', $auditLog[1]['changes']['description']['from']);
        $this->assertEquals('Updated', $auditLog[1]['changes']['description']['to']);
    }

    public function test_does_not_append_audit_log_when_no_changes(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'description' => 'Same description',
            'audit_log' => [['at' => now()->toIso8601String(), 'by' => 'U_CREATOR', 'changes' => ['created' => true]]],
        ]);
        $actorId = 'U_UPDATER';

        $this->action->handle($order, ['description' => 'Same description'], $actorId);

        $auditLog = $order->fresh()->audit_log;
        $this->assertCount(1, $auditLog);
    }

    public function test_updates_price_final(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'price_final' => null,
        ]);
        $actorId = 'U_ACTOR';

        $result = $this->action->handle($order, ['price_final' => 15.50], $actorId);

        $this->assertEquals(15.50, (float) $result->price_final);
    }

    public function test_detects_float_changes_correctly(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'price_estimated' => 10.00,
            'audit_log' => [['at' => now()->toIso8601String(), 'by' => 'U_CREATOR', 'changes' => ['created' => true]]],
        ]);
        $actorId = 'U_ACTOR';

        $this->action->handle($order, ['price_estimated' => 10.50], $actorId);

        $auditLog = $order->fresh()->audit_log;
        $this->assertCount(2, $auditLog);
        $this->assertArrayHasKey('price_estimated', $auditLog[1]['changes']);
    }

    public function test_does_not_detect_change_for_same_float_value(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'price_estimated' => 10.00,
            'audit_log' => [['at' => now()->toIso8601String(), 'by' => 'U_CREATOR', 'changes' => ['created' => true]]],
        ]);
        $actorId = 'U_ACTOR';

        $this->action->handle($order, ['price_estimated' => 10.00], $actorId);

        $auditLog = $order->fresh()->audit_log;
        $this->assertCount(1, $auditLog);
    }

    public function test_handles_null_to_value_transition(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'notes' => null,
            'audit_log' => [['at' => now()->toIso8601String(), 'by' => 'U_CREATOR', 'changes' => ['created' => true]]],
        ]);
        $actorId = 'U_ACTOR';

        $this->action->handle($order, ['notes' => 'New notes'], $actorId);

        $auditLog = $order->fresh()->audit_log;
        $this->assertCount(2, $auditLog);
        $this->assertEquals(null, $auditLog[1]['changes']['notes']['from']);
        $this->assertEquals('New notes', $auditLog[1]['changes']['notes']['to']);
    }
}
