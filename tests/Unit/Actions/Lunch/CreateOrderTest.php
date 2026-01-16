<?php

namespace Tests\Unit\Actions\Lunch;

use App\Actions\Lunch\CreateOrder;
use App\Models\LunchDay;
use App\Models\LunchDayProposal;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    private CreateOrder $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateOrder;
    }

    public function test_creates_order_for_open_day(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()->for($day)->create();
        $userId = 'U_CUSTOMER';
        $data = [
            'description' => 'Burger with fries',
            'price_estimated' => 15.50,
            'notes' => 'No onions please',
        ];

        $order = $this->action->handle($proposal, $userId, $data);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($proposal->id, $order->lunch_day_proposal_id);
        $this->assertEquals($userId, $order->provider_user_id);
        $this->assertEquals('Burger with fries', $order->description);
        $this->assertEquals(15.50, (float) $order->price_estimated);
        $this->assertEquals('No onions please', $order->notes);
    }

    public function test_throws_exception_for_locked_day(): void
    {
        $day = LunchDay::factory()->locked()->create();
        $proposal = LunchDayProposal::factory()->for($day)->create();
        $data = [
            'description' => 'Some food',
            'price_estimated' => 10.00,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lunch day is not open.');

        $this->action->handle($proposal, 'U_USER', $data);
    }

    public function test_throws_exception_for_closed_day(): void
    {
        $day = LunchDay::factory()->closed()->create();
        $proposal = LunchDayProposal::factory()->for($day)->create();
        $data = [
            'description' => 'Some food',
            'price_estimated' => 10.00,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lunch day is not open.');

        $this->action->handle($proposal, 'U_USER', $data);
    }

    public function test_creates_order_with_audit_log(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()->for($day)->create();
        $userId = 'U_CUSTOMER';
        $data = [
            'description' => 'Pizza',
            'price_estimated' => 12.00,
        ];

        $order = $this->action->handle($proposal, $userId, $data);

        $auditLog = $order->audit_log;
        $this->assertCount(1, $auditLog);
        $this->assertEquals($userId, $auditLog[0]['by']);
        $this->assertTrue($auditLog[0]['changes']['created']);
    }

    public function test_creates_order_with_null_notes(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()->for($day)->create();
        $data = [
            'description' => 'Salad',
            'price_estimated' => 8.50,
        ];

        $order = $this->action->handle($proposal, 'U_USER', $data);

        $this->assertNull($order->notes);
    }

    public function test_creates_order_with_explicit_null_notes(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()->for($day)->create();
        $data = [
            'description' => 'Salad',
            'price_estimated' => 8.50,
            'notes' => null,
        ];

        $order = $this->action->handle($proposal, 'U_USER', $data);

        $this->assertNull($order->notes);
    }

    public function test_allows_multiple_orders_from_different_users(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()->for($day)->create();
        $data = [
            'description' => 'Same menu',
            'price_estimated' => 10.00,
        ];

        $order1 = $this->action->handle($proposal, 'U_USER1', $data);
        $order2 = $this->action->handle($proposal, 'U_USER2', $data);

        $this->assertNotEquals($order1->id, $order2->id);
        $this->assertEquals(2, Order::count());
    }
}
