<?php

namespace Tests\Unit\Services\Slack\Data;

use App\Enums\DashboardState;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\VendorProposal;
use App\Services\Slack\Data\DashboardContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DashboardContextTest extends TestCase
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

    private function makeContext(array $overrides = []): DashboardContext
    {
        $session = $overrides['session'] ?? LunchSession::factory()->for($this->organization)->open()->create();

        return new DashboardContext(
            state: $overrides['state'] ?? DashboardState::NoProposal,
            session: $session,
            userId: $overrides['userId'] ?? 'U_TEST',
            date: $overrides['date'] ?? Carbon::today(),
            isToday: $overrides['isToday'] ?? true,
            isAdmin: $overrides['isAdmin'] ?? false,
            workspaceName: $overrides['workspaceName'] ?? 'Test Workspace',
            locale: $overrides['locale'] ?? 'en',
            proposals: $overrides['proposals'] ?? new Collection,
            openProposals: $overrides['openProposals'] ?? new Collection,
            myProposalsInCharge: $overrides['myProposalsInCharge'] ?? new Collection,
            myOrder: $overrides['myOrder'] ?? null,
            myOrderProposal: $overrides['myOrderProposal'] ?? null,
        );
    }

    public function test_has_open_proposals_returns_true_when_not_empty(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $context = $this->makeContext(['openProposals' => new Collection([$proposal])]);

        $this->assertTrue($context->hasOpenProposals());
    }

    public function test_has_open_proposals_returns_false_when_empty(): void
    {
        $context = $this->makeContext(['openProposals' => new Collection]);

        $this->assertFalse($context->hasOpenProposals());
    }

    public function test_has_proposals_returns_true_when_not_empty(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $context = $this->makeContext(['proposals' => new Collection([$proposal])]);

        $this->assertTrue($context->hasProposals());
    }

    public function test_has_order_returns_true_when_order_set(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $order = Order::factory()->for($proposal)->create(['organization_id' => $this->organization->id]);
        $context = $this->makeContext(['myOrder' => $order]);

        $this->assertTrue($context->hasOrder());
    }

    public function test_has_order_returns_false_when_null(): void
    {
        $context = $this->makeContext(['myOrder' => null]);

        $this->assertFalse($context->hasOrder());
    }

    public function test_is_in_charge_returns_true_when_proposals_in_charge(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $context = $this->makeContext(['myProposalsInCharge' => new Collection([$proposal])]);

        $this->assertTrue($context->isInCharge());
    }

    public function test_can_create_proposal_requires_today_and_open(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $context = $this->makeContext(['session' => $session, 'isToday' => true]);

        $this->assertTrue($context->canCreateProposal());

        $lockedSession = LunchSession::factory()->for($this->organization)->locked()->create();
        $context2 = $this->makeContext(['session' => $lockedSession, 'isToday' => true]);

        $this->assertFalse($context2->canCreateProposal());
    }

    public function test_can_close_session_allows_admin(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $context = $this->makeContext(['session' => $session, 'isToday' => true, 'isAdmin' => true]);

        $this->assertTrue($context->canCloseSession());
    }

    public function test_can_close_session_allows_in_charge_user(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()->for($this->organization)->for($session)->create();
        $context = $this->makeContext([
            'session' => $session,
            'isToday' => true,
            'isAdmin' => false,
            'myProposalsInCharge' => new Collection([$proposal]),
        ]);

        $this->assertTrue($context->canCloseSession());
    }

    public function test_can_close_session_denies_when_closed(): void
    {
        $session = LunchSession::factory()->for($this->organization)->closed()->create();
        $context = $this->makeContext(['session' => $session, 'isToday' => true, 'isAdmin' => true]);

        $this->assertFalse($context->canCloseSession());
    }

    public function test_to_private_metadata_contains_required_keys(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $context = $this->makeContext(['session' => $session, 'userId' => 'U_META']);

        $metadata = $context->toPrivateMetadata();

        $this->assertArrayHasKey('tenant_id', $metadata);
        $this->assertArrayHasKey('date', $metadata);
        $this->assertArrayHasKey('lunch_session_id', $metadata);
        $this->assertArrayHasKey('origin', $metadata);
        $this->assertArrayHasKey('user_id', $metadata);
        $this->assertArrayHasKey('state', $metadata);
        $this->assertEquals('U_META', $metadata['user_id']);
        $this->assertEquals($session->id, $metadata['lunch_session_id']);
    }

    public function test_to_private_metadata_json_is_valid_json(): void
    {
        $context = $this->makeContext();

        $json = $context->toPrivateMetadataJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('lunch_session_id', $decoded);
    }
}
