<?php

namespace Tests\Feature\Policies;

use App\Authorization\Actor;
use App\Enums\ProposalStatus;
use App\Models\Order;
use App\Models\VendorProposal;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private OrderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new OrderPolicy;
    }

    public function test_author_can_update_own_order_when_proposal_is_open(): void
    {
        $authorId = 'U_AUTHOR';
        $proposal = VendorProposal::factory()->create([
            'status' => ProposalStatus::Open,
        ]);
        $order = Order::factory()->for($proposal, 'proposal')->create([
            'provider_user_id' => $authorId,
        ]);
        $actor = new Actor($authorId, false);

        $this->assertTrue($this->policy->update($actor, $order));
    }

    public function test_author_cannot_update_own_order_when_proposal_is_not_open(): void
    {
        $authorId = 'U_AUTHOR';
        $proposal = VendorProposal::factory()->create([
            'status' => ProposalStatus::Ordering,
        ]);
        $order = Order::factory()->for($proposal, 'proposal')->create([
            'provider_user_id' => $authorId,
        ]);
        $actor = new Actor($authorId, false);

        $this->assertFalse($this->policy->update($actor, $order));
    }

    public function test_author_cannot_update_when_proposal_is_closed(): void
    {
        $authorId = 'U_AUTHOR';
        $proposal = VendorProposal::factory()->create([
            'status' => ProposalStatus::Closed,
        ]);
        $order = Order::factory()->for($proposal, 'proposal')->create([
            'provider_user_id' => $authorId,
        ]);
        $actor = new Actor($authorId, false);

        $this->assertFalse($this->policy->update($actor, $order));
    }

    public function test_non_author_cannot_update_order(): void
    {
        $proposal = VendorProposal::factory()->create([
            'status' => ProposalStatus::Open,
        ]);
        $order = Order::factory()->for($proposal, 'proposal')->create([
            'provider_user_id' => 'U_AUTHOR',
        ]);
        $actor = new Actor('U_OTHER_USER', false);

        $this->assertFalse($this->policy->update($actor, $order));
    }

    public function test_admin_can_update_any_order(): void
    {
        $proposal = VendorProposal::factory()->create([
            'status' => ProposalStatus::Closed,
        ]);
        $order = Order::factory()->for($proposal, 'proposal')->create([
            'provider_user_id' => 'U_AUTHOR',
        ]);
        $actor = new Actor('U_ADMIN', true);

        $this->assertTrue($this->policy->update($actor, $order));
    }

    public function test_runner_can_set_final_price(): void
    {
        $runnerId = 'U_RUNNER';
        $proposal = VendorProposal::factory()->create([
            'runner_user_id' => $runnerId,
        ]);
        $order = Order::factory()->for($proposal, 'proposal')->create([
            'provider_user_id' => 'U_SOME_USER',
        ]);
        $actor = new Actor($runnerId, false);

        $this->assertTrue($this->policy->setFinalPrice($actor, $order));
    }

    public function test_orderer_can_set_final_price(): void
    {
        $ordererId = 'U_ORDERER';
        $proposal = VendorProposal::factory()->create([
            'orderer_user_id' => $ordererId,
        ]);
        $order = Order::factory()->for($proposal, 'proposal')->create([
            'provider_user_id' => 'U_SOME_USER',
        ]);
        $actor = new Actor($ordererId, false);

        $this->assertTrue($this->policy->setFinalPrice($actor, $order));
    }

    public function test_non_responsible_user_cannot_set_final_price(): void
    {
        $proposal = VendorProposal::factory()->create([
            'runner_user_id' => 'U_RUNNER',
            'orderer_user_id' => null,
        ]);
        $order = Order::factory()->for($proposal, 'proposal')->create([
            'provider_user_id' => 'U_ORDER_AUTHOR',
        ]);
        $actor = new Actor('U_ORDER_AUTHOR', false);

        $this->assertFalse($this->policy->setFinalPrice($actor, $order));
    }

    public function test_admin_can_set_final_price_for_any_order(): void
    {
        $proposal = VendorProposal::factory()->create([
            'runner_user_id' => 'U_RUNNER',
        ]);
        $order = Order::factory()->for($proposal, 'proposal')->create([
            'provider_user_id' => 'U_SOME_USER',
        ]);
        $actor = new Actor('U_ADMIN', true);

        $this->assertTrue($this->policy->setFinalPrice($actor, $order));
    }

    public function test_nobody_can_set_final_price_without_responsible_user(): void
    {
        $proposal = VendorProposal::factory()->create([
            'runner_user_id' => null,
            'orderer_user_id' => null,
        ]);
        $order = Order::factory()->for($proposal, 'proposal')->create([
            'provider_user_id' => 'U_SOME_USER',
        ]);
        $actor = new Actor('U_ANY_USER', false);

        $this->assertFalse($this->policy->setFinalPrice($actor, $order));
    }
}
