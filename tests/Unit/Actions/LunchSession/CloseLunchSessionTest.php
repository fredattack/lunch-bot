<?php

namespace Tests\Unit\Actions\LunchSession;

use App\Actions\LunchSession\CloseLunchSession;
use App\Enums\LunchSessionStatus;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Organization;
use App\Models\VendorProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseLunchSessionTest extends TestCase
{
    use RefreshDatabase;

    private CloseLunchSession $action;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CloseLunchSession;
        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_closes_session(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create();

        $result = $this->action->handle($session);

        $this->assertEquals(LunchSessionStatus::Closed, $result->status);
        $this->assertDatabaseHas('lunch_sessions', [
            'id' => $session->id,
            'status' => LunchSessionStatus::Closed->value,
        ]);
    }

    public function test_closes_all_proposals_when_session_closes(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create();

        $proposal1 = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create(['status' => ProposalStatus::Open]);

        $proposal2 = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create(['status' => ProposalStatus::Ordering]);

        $proposal3 = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create(['status' => ProposalStatus::Placed]);

        $this->action->handle($session);

        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal1->id,
            'status' => ProposalStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal2->id,
            'status' => ProposalStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal3->id,
            'status' => ProposalStatus::Closed->value,
        ]);
    }

    public function test_handles_session_without_proposals(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create();

        $result = $this->action->handle($session);

        $this->assertEquals(LunchSessionStatus::Closed, $result->status);
    }

    public function test_closes_already_closed_session(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->closed()
            ->create();

        $result = $this->action->handle($session);

        $this->assertEquals(LunchSessionStatus::Closed, $result->status);
    }

    public function test_closes_locked_session(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->locked()
            ->create();

        $result = $this->action->handle($session);

        $this->assertEquals(LunchSessionStatus::Closed, $result->status);
    }

    public function test_returns_the_session_instance(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create();

        $result = $this->action->handle($session);

        $this->assertInstanceOf(LunchSession::class, $result);
        $this->assertEquals($session->id, $result->id);
    }
}
