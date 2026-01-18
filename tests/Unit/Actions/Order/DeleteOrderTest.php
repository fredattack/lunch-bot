<?php

namespace Tests\Unit\Actions\Order;

use App\Actions\Order\DeleteOrder;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\VendorProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DeleteOrderTest extends TestCase
{
    use RefreshDatabase;

    private DeleteOrder $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new DeleteOrder;
    }

    public function test_deletes_order_for_owner(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->action->handle($order, 'U_OWNER');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_throws_exception_when_user_is_not_owner(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You can only delete your own order.');

        $this->action->handle($order, 'U_OTHER');
    }

    public function test_throws_exception_when_session_is_closed(): void
    {
        $session = LunchSession::factory()->closed()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lunch session is closed.');

        $this->action->handle($order, 'U_OWNER');
    }

    public function test_allows_deletion_when_session_is_locked(): void
    {
        $session = LunchSession::factory()->locked()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->action->handle($order, 'U_OWNER');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }
}
