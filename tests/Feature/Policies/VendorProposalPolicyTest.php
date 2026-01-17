<?php

namespace Tests\Feature\Policies;

use App\Authorization\Actor;
use App\Models\VendorProposal;
use App\Policies\VendorProposalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorProposalPolicyTest extends TestCase
{
    use RefreshDatabase;

    private VendorProposalPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new VendorProposalPolicy;
    }

    public function test_runner_can_transfer_responsibility(): void
    {
        $runnerId = 'U_RUNNER';
        $proposal = VendorProposal::factory()->withRunner($runnerId)->create();
        $actor = new Actor($runnerId, false);

        $this->assertTrue($this->policy->transferResponsibility($actor, $proposal));
    }

    public function test_orderer_can_transfer_responsibility(): void
    {
        $ordererId = 'U_ORDERER';
        $proposal = VendorProposal::factory()->withOrderer($ordererId)->create();
        $actor = new Actor($ordererId, false);

        $this->assertTrue($this->policy->transferResponsibility($actor, $proposal));
    }

    public function test_non_responsible_user_cannot_transfer_responsibility(): void
    {
        $proposal = VendorProposal::factory()->withRunner('U_RUNNER')->create();
        $actor = new Actor('U_OTHER_USER', false);

        $this->assertFalse($this->policy->transferResponsibility($actor, $proposal));
    }

    public function test_admin_can_transfer_responsibility_regardless_of_role(): void
    {
        $proposal = VendorProposal::factory()->withRunner('U_RUNNER')->create();
        $actor = new Actor('U_ADMIN', true);

        $this->assertTrue($this->policy->transferResponsibility($actor, $proposal));
    }

    public function test_non_admin_cannot_transfer_when_no_responsible_user(): void
    {
        $proposal = VendorProposal::factory()->create([
            'runner_user_id' => null,
            'orderer_user_id' => null,
        ]);
        $actor = new Actor('U_ANY_USER', false);

        $this->assertFalse($this->policy->transferResponsibility($actor, $proposal));
    }

    public function test_admin_can_transfer_when_no_responsible_user(): void
    {
        $proposal = VendorProposal::factory()->create([
            'runner_user_id' => null,
            'orderer_user_id' => null,
        ]);
        $actor = new Actor('U_ADMIN', true);

        $this->assertTrue($this->policy->transferResponsibility($actor, $proposal));
    }

    public function test_runner_can_close_proposal(): void
    {
        $runnerId = 'U_RUNNER';
        $proposal = VendorProposal::factory()->withRunner($runnerId)->create();
        $actor = new Actor($runnerId, false);

        $this->assertTrue($this->policy->close($actor, $proposal));
    }

    public function test_orderer_can_close_proposal(): void
    {
        $ordererId = 'U_ORDERER';
        $proposal = VendorProposal::factory()->withOrderer($ordererId)->create();
        $actor = new Actor($ordererId, false);

        $this->assertTrue($this->policy->close($actor, $proposal));
    }

    public function test_non_responsible_user_cannot_close_proposal(): void
    {
        $proposal = VendorProposal::factory()->withRunner('U_RUNNER')->create();
        $actor = new Actor('U_OTHER_USER', false);

        $this->assertFalse($this->policy->close($actor, $proposal));
    }

    public function test_admin_can_close_proposal_regardless_of_role(): void
    {
        $proposal = VendorProposal::factory()->withRunner('U_RUNNER')->create();
        $actor = new Actor('U_ADMIN', true);

        $this->assertTrue($this->policy->close($actor, $proposal));
    }

    public function test_non_admin_cannot_close_when_no_responsible_user(): void
    {
        $proposal = VendorProposal::factory()->create([
            'runner_user_id' => null,
            'orderer_user_id' => null,
        ]);
        $actor = new Actor('U_ANY_USER', false);

        $this->assertFalse($this->policy->close($actor, $proposal));
    }

    public function test_admin_can_close_when_no_responsible_user(): void
    {
        $proposal = VendorProposal::factory()->create([
            'runner_user_id' => null,
            'orderer_user_id' => null,
        ]);
        $actor = new Actor('U_ADMIN', true);

        $this->assertTrue($this->policy->close($actor, $proposal));
    }

    public function test_runner_takes_precedence_over_orderer_for_responsibility(): void
    {
        $runnerId = 'U_RUNNER';
        $ordererId = 'U_ORDERER';
        $proposal = VendorProposal::factory()->create([
            'runner_user_id' => $runnerId,
            'orderer_user_id' => $ordererId,
        ]);

        $runnerActor = new Actor($runnerId, false);
        $ordererActor = new Actor($ordererId, false);

        $this->assertTrue($this->policy->close($runnerActor, $proposal));
        $this->assertFalse($this->policy->close($ordererActor, $proposal));
    }
}
