<?php

namespace Tests\Unit\Services\Slack;

use App\Enums\LunchSessionStatus;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Vendor;
use App\Models\VendorProposal;
use App\Services\Slack\DashboardBlockBuilder;
use App\Services\Slack\Handlers\BaseInteractionHandler;
use App\Services\Slack\Handlers\OrderInteractionHandler;
use App\Services\Slack\SlackBlockBuilder;
use App\Services\Slack\SlackInteractionHandler;
use App\Services\Slack\SlackMessenger;
use App\Services\Slack\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use ReflectionMethod;
use Tests\TestCase;

class SlackInteractionHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\After]
    protected function closeMockery(): void
    {
        if ($container = Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }

        Mockery::close();
    }

    private SlackInteractionHandler $handler;

    private MockInterface $slack;

    private MockInterface $messenger;

    private MockInterface $blocks;

    private MockInterface $dashboardBlocks;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);

        $this->slack = Mockery::mock(SlackService::class);
        $this->messenger = Mockery::mock(SlackMessenger::class);
        $this->blocks = Mockery::mock(SlackBlockBuilder::class);
        $this->dashboardBlocks = Mockery::mock(DashboardBlockBuilder::class);

        $this->slack->shouldReceive('teamInfo')->andReturn(['locale' => 'en']);

        $this->app->instance(SlackService::class, $this->slack);
        $this->app->instance(SlackMessenger::class, $this->messenger);
        $this->app->instance(SlackBlockBuilder::class, $this->blocks);
        $this->app->instance(DashboardBlockBuilder::class, $this->dashboardBlocks);

        $this->handler = $this->app->make(SlackInteractionHandler::class);
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_parse_price_with_comma_decimal(): void
    {
        $result = $this->invokeParsePrice('12,50');

        $this->assertEquals(12.5, $result);
    }

    public function test_parse_price_with_dot_decimal(): void
    {
        $result = $this->invokeParsePrice('12.50');

        $this->assertEquals(12.5, $result);
    }

    public function test_parse_price_with_integer(): void
    {
        $result = $this->invokeParsePrice('12');

        $this->assertEquals(12.0, $result);
    }

    public function test_parse_price_with_empty_string(): void
    {
        $result = $this->invokeParsePrice('');

        $this->assertNull($result);
    }

    public function test_parse_price_with_null(): void
    {
        $result = $this->invokeParsePrice(null);

        $this->assertNull($result);
    }

    public function test_parse_price_with_invalid_string(): void
    {
        $result = $this->invokeParsePrice('abc');

        $this->assertNull($result);
    }

    public function test_parse_price_with_mixed_invalid(): void
    {
        $result = $this->invokeParsePrice('12abc');

        $this->assertNull($result);
    }

    public function test_parse_price_with_multiple_dots(): void
    {
        $result = $this->invokeParsePrice('12.50.00');

        $this->assertNull($result);
    }

    public function test_parse_price_with_whitespace(): void
    {
        $result = $this->invokeParsePrice(' 15 ');

        $this->assertEquals(15.0, $result);
    }

    public function test_parse_price_with_zero(): void
    {
        $result = $this->invokeParsePrice('0');

        $this->assertEquals(0.0, $result);
    }

    public function test_handle_interactivity_returns_200_for_block_actions(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->messenger->shouldReceive('postEphemeral')->andReturnNull();

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U123'],
            'channel' => ['id' => 'C123'],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'unknown_action', 'value' => (string) $session->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_interactivity_returns_200_for_view_submission(): void
    {
        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U123'],
            'view' => [
                'callback_id' => 'unknown_callback',
                'state' => ['values' => []],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_interactivity_returns_200_for_unknown_type(): void
    {
        $payload = [
            'type' => 'unknown_type',
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_event_logs_event_type(): void
    {
        $payload = ['type' => 'event_callback', 'event' => ['type' => 'message']];

        $this->handler->handleEvent($payload);

        $this->assertTrue(true);
    }

    public function test_claim_runner_assigns_role_and_updates_message(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create(['status' => ProposalStatus::Open]);

        $this->messenger->shouldReceive('updateProposalMessage')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id));

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_RUNNER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'claim_runner', 'value' => (string) $proposal->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal->id,
            'runner_user_id' => 'U_RUNNER',
        ]);
    }

    public function test_claim_runner_fails_when_already_assigned(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_EXISTING')
            ->create();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_NEW', 'Role deja attribue.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_NEW'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'claim_runner', 'value' => (string) $proposal->id],
            ],
        ];

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal->id,
            'runner_user_id' => 'U_EXISTING',
        ]);
    }

    public function test_claim_orderer_assigns_role(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create(['status' => ProposalStatus::Open]);

        $this->messenger->shouldReceive('updateProposalMessage')->once();

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_ORDERER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'claim_orderer', 'value' => (string) $proposal->id],
            ],
        ];

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal->id,
            'orderer_user_id' => 'U_ORDERER',
        ]);
    }

    public function test_block_action_fails_on_locked_session(): void
    {
        $session = LunchSession::factory()->for($this->organization)->locked()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_USER', 'Les commandes sont verrouillees.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'claim_runner', 'value' => (string) $proposal->id],
            ],
        ];

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseMissing('vendor_proposals', [
            'id' => $proposal->id,
            'runner_user_id' => 'U_USER',
        ]);
    }

    public function test_close_day_requires_permission(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_RANDOM')
            ->andReturn(false);

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_RANDOM', 'Seul le runner/orderer ou un admin peut cloturer.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_RANDOM'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'close_day', 'value' => (string) $session->id],
            ],
        ];

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('lunch_sessions', [
            'id' => $session->id,
            'status' => LunchSessionStatus::Open->value,
        ]);
    }

    public function test_close_day_allowed_for_admin(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_ADMIN')
            ->andReturn(true);

        $this->messenger->shouldReceive('postClosureSummary')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $session->id));

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_ADMIN'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'close_day', 'value' => (string) $session->id],
            ],
        ];

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('lunch_sessions', [
            'id' => $session->id,
            'status' => LunchSessionStatus::Closed->value,
        ]);
    }

    public function test_close_day_allowed_for_runner(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_RUNNER')
            ->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_RUNNER')
            ->andReturn(false);

        $this->messenger->shouldReceive('postClosureSummary')->once();

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_RUNNER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'close_day', 'value' => (string) $session->id],
            ],
        ];

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('lunch_sessions', [
            'id' => $session->id,
            'status' => LunchSessionStatus::Closed->value,
        ]);
    }

    public function test_open_proposal_modal_on_open_session(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        Vendor::factory()->for($this->organization)->create(['active' => true]);

        $this->blocks->shouldReceive('proposalModal')
            ->once()
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('openModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'open_proposal_modal', 'value' => (string) $session->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_open_proposal_modal_fails_when_no_active_vendors(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_USER', 'Aucune enseigne active pour le moment.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'open_proposal_modal', 'value' => (string) $session->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_order_delete_removes_order_and_updates_message(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->messenger->shouldReceive('updateProposalMessage')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id));

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_OWNER', 'Commande supprimee.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_OWNER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'order.delete', 'value' => (string) $order->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_order_delete_fails_for_non_owner(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_OTHER', 'You can only delete your own order.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_OTHER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'order.delete', 'value' => (string) $order->id],
            ],
        ];

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_proposal_open_manage_opens_modal(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->for($vendor)
            ->create(['status' => ProposalStatus::Open]);

        $this->blocks->shouldReceive('proposalManageModal')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id), 'U_USER')
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('pushModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'proposal.open_manage', 'value' => (string) $proposal->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_proposal_open_manage_fails_on_locked_session(): void
    {
        $session = LunchSession::factory()->for($this->organization)->locked()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_USER', 'Les commandes sont verrouillees.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'proposal.open_manage', 'value' => (string) $proposal->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_proposal_take_charge_assigns_runner_for_pickup(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->pickup()
            ->create(['status' => ProposalStatus::Open]);

        $this->messenger->shouldReceive('updateProposalMessage')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id));

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_USER', 'Vous etes maintenant runner pour cette commande.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'proposal.take_charge', 'value' => (string) $proposal->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal->id,
            'runner_user_id' => 'U_USER',
        ]);
    }

    public function test_proposal_take_charge_assigns_orderer_for_delivery(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->delivery()
            ->create(['status' => ProposalStatus::Open]);

        $this->messenger->shouldReceive('updateProposalMessage')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id));

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_USER', 'Vous etes maintenant orderer pour cette commande.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'proposal.take_charge', 'value' => (string) $proposal->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal->id,
            'orderer_user_id' => 'U_USER',
        ]);
    }

    public function test_proposal_take_charge_fails_when_already_assigned(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->pickup()
            ->withRunner('U_EXISTING')
            ->create();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_NEW', 'Un responsable est deja assigne.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_NEW'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'proposal.take_charge', 'value' => (string) $proposal->id],
            ],
        ];

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal->id,
            'runner_user_id' => 'U_EXISTING',
        ]);
    }

    public function test_proposal_take_charge_fails_on_locked_session(): void
    {
        $session = LunchSession::factory()->for($this->organization)->locked()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_USER', 'Les commandes sont verrouillees.');

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'proposal.take_charge', 'value' => (string) $proposal->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_open_order_for_proposal_opens_create_modal_for_new_user(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        $this->blocks->shouldReceive('orderModal')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id), null, false, false)
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('openModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_NEW_USER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'open_order_for_proposal', 'value' => (string) $proposal->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_open_order_for_proposal_opens_edit_modal_for_existing_order(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_EXISTING',
        ]);

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_EXISTING')
            ->andReturn(false);

        $this->blocks->shouldReceive('orderModal')
            ->once()
            ->with(
                Mockery::on(fn ($p) => $p->id === $proposal->id),
                Mockery::on(fn ($o) => $o->id === $order->id),
                false,
                true
            )
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('openModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_EXISTING'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'open_order_for_proposal', 'value' => (string) $proposal->id],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_open_lunch_dashboard_opens_dashboard(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_USER')
            ->andReturn(false);

        $this->dashboardBlocks->shouldReceive('buildModal')
            ->once()
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('openModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U_USER'],
            'channel' => ['id' => $session->provider_channel_id],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => 'open_lunch_dashboard', 'value' => $session->date->format('Y-m-d')],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_order_create_sends_message_for_first_order(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create(['provider_message_ts' => null]);

        $this->messenger->shouldReceive('postOrderCreatedMessage')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id), 'U_CREATOR', false);

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with(Mockery::any(), 'U_CREATOR', 'Commande enregistree.', Mockery::any());

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_CREATOR'],
            'view' => [
                'callback_id' => 'order_create',
                'private_metadata' => json_encode([
                    'proposal_id' => $proposal->id,
                    'lunch_session_id' => $session->id,
                ]),
                'state' => [
                    'values' => [
                        'description' => ['description' => ['value' => 'Big Mac Menu']],
                        'price_estimated' => ['price_estimated' => ['value' => '12.50']],
                        'notes' => ['notes' => ['value' => '']],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('orders', [
            'vendor_proposal_id' => $proposal->id,
            'provider_user_id' => 'U_CREATOR',
            'description' => 'Big Mac Menu',
        ]);
    }

    public function test_dashboard_start_from_catalog_opens_proposal_modal_with_vendors(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        Vendor::factory()->for($this->organization)->create(['active' => true]);

        $this->blocks->shouldReceive('proposalModal')
            ->once()
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('pushModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = $this->blockActionPayload('dashboard.start_from_catalog', (string) $session->id, $session->provider_channel_id);

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_dashboard_start_from_catalog_opens_propose_restaurant_when_no_vendors(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->blocks->shouldReceive('proposeRestaurantModal')
            ->once()
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('pushModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = $this->blockActionPayload('dashboard.start_from_catalog', (string) $session->id, $session->provider_channel_id);

        $this->handler->handleInteractivity($payload);
    }

    public function test_dashboard_create_proposal_opens_restaurant_modal(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->blocks->shouldReceive('proposeRestaurantModal')
            ->once()
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('pushModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = $this->blockActionPayload('dashboard.create_proposal', (string) $session->id, $session->provider_channel_id);

        $this->handler->handleInteractivity($payload);
    }

    public function test_dashboard_join_proposal_opens_order_modal(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        $this->blocks->shouldReceive('orderModal')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id), null, false, false)
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('pushModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = $this->blockActionPayload('dashboard.join_proposal', (string) $proposal->id, $session->provider_channel_id);

        $this->handler->handleInteractivity($payload);
    }

    public function test_dashboard_choose_favorite_opens_proposal_modal(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        Vendor::factory()->for($this->organization)->create(['active' => true]);

        $this->blocks->shouldReceive('proposalModal')
            ->once()
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('pushModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = $this->blockActionPayload('dashboard_choose_favorite', (string) $session->id, $session->provider_channel_id);

        $this->handler->handleInteractivity($payload);
    }

    public function test_dashboard_claim_responsible_assigns_runner(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create(['status' => ProposalStatus::Open]);

        $this->messenger->shouldReceive('updateProposalMessage')->once();

        $payload = $this->blockActionPayload('dashboard_claim_responsible', (string) $proposal->id, $session->provider_channel_id);

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal->id,
            'runner_user_id' => 'U_USER',
        ]);
    }

    public function test_order_open_edit_opens_edit_modal(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_USER',
        ]);

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_USER')
            ->andReturn(false);

        $this->blocks->shouldReceive('orderModal')
            ->once()
            ->with(
                Mockery::on(fn ($p) => $p->id === $proposal->id),
                Mockery::on(fn ($o) => $o->id === $order->id),
                false,
                true
            )
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('pushModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = $this->blockActionPayload('order.open_edit', (string) $order->id, $session->provider_channel_id);

        $this->handler->handleInteractivity($payload);
    }

    public function test_dashboard_my_order_opens_edit_modal_for_existing_order(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_USER',
        ]);

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_USER')
            ->andReturn(false);

        $this->blocks->shouldReceive('orderModal')
            ->once()
            ->with(
                Mockery::on(fn ($p) => $p->id === $proposal->id),
                Mockery::on(fn ($o) => $o->id === $order->id),
                false,
                true
            )
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('pushModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = $this->blockActionPayload('dashboard_my_order', (string) $proposal->id, $session->provider_channel_id);

        $this->handler->handleInteractivity($payload);
    }

    public function test_dashboard_my_order_posts_ephemeral_when_no_order(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_USER', 'Aucune commande a modifier.');

        $payload = $this->blockActionPayload('dashboard_my_order', (string) $proposal->id, $session->provider_channel_id);

        $this->handler->handleInteractivity($payload);
    }

    public function test_proposal_open_recap_opens_modal_for_runner(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->for($vendor)
            ->withRunner('U_RUNNER')
            ->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_RUNNER')
            ->andReturn(false);

        $this->blocks->shouldReceive('recapModal')
            ->once()
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('pushModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = $this->blockActionPayload('proposal.open_recap', (string) $proposal->id, $session->provider_channel_id, 'U_RUNNER');

        $this->handler->handleInteractivity($payload);
    }

    public function test_proposal_open_recap_denied_for_non_runner(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_RUNNER')
            ->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_OTHER')
            ->andReturn(false);

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_OTHER', 'Seul le responsable peut voir le recapitulatif.');

        $payload = $this->blockActionPayload('proposal.open_recap', (string) $proposal->id, $session->provider_channel_id, 'U_OTHER');

        $this->handler->handleInteractivity($payload);
    }

    public function test_proposal_close_closes_for_runner(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_RUNNER')
            ->create(['status' => ProposalStatus::Open]);

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_RUNNER')
            ->andReturn(false);

        $this->messenger->shouldReceive('updateProposalMessage')->once();

        $payload = $this->blockActionPayload('proposal.close', (string) $proposal->id, $session->provider_channel_id, 'U_RUNNER');

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal->id,
            'status' => ProposalStatus::Closed->value,
        ]);
    }

    public function test_proposal_close_denied_for_non_runner(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_RUNNER')
            ->create(['status' => ProposalStatus::Open]);

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_OTHER')
            ->andReturn(false);

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_OTHER', 'Seul le responsable peut cloturer.');

        $payload = $this->blockActionPayload('proposal.close', (string) $proposal->id, $session->provider_channel_id, 'U_OTHER');

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal->id,
            'status' => ProposalStatus::Open->value,
        ]);
    }

    public function test_open_delegate_modal_opens_for_user_with_role(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_RUNNER')
            ->create();

        $this->blocks->shouldReceive('delegateModal')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id), 'runner')
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('openModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = $this->blockActionPayload('open_delegate_modal', (string) $proposal->id, $session->provider_channel_id, 'U_RUNNER');

        $this->handler->handleInteractivity($payload);
    }

    public function test_open_delegate_modal_denied_for_user_without_role(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_RUNNER')
            ->create();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_OTHER', "Vous n'avez pas de role a deleguer.");

        $payload = $this->blockActionPayload('open_delegate_modal', (string) $proposal->id, $session->provider_channel_id, 'U_OTHER');

        $this->handler->handleInteractivity($payload);
    }

    public function test_open_summary_posts_summary_for_runner(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_RUNNER')
            ->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_RUNNER')
            ->andReturn(false);

        $this->messenger->shouldReceive('postSummary')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id));

        $payload = $this->blockActionPayload('open_summary', (string) $proposal->id, $session->provider_channel_id, 'U_RUNNER');

        $this->handler->handleInteractivity($payload);
    }

    public function test_vendors_list_opens_modal(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        Vendor::factory()->for($this->organization)->create(['active' => true]);

        $this->blocks->shouldReceive('vendorsListModal')
            ->once()
            ->andReturn(['type' => 'modal']);

        $this->messenger->shouldReceive('pushModal')
            ->once()
            ->with('trigger123', ['type' => 'modal']);

        $payload = $this->blockActionPayload('dashboard.vendors_list', (string) $session->id, $session->provider_channel_id);

        $this->handler->handleInteractivity($payload);
    }

    public function test_proposal_create_submission_creates_proposal(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $vendor = Vendor::factory()->for($this->organization)->create(['active' => true]);

        $this->blocks->shouldReceive('orderModal')
            ->once()
            ->andReturn(['type' => 'modal']);

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_USER'],
            'view' => [
                'callback_id' => 'proposal_create',
                'private_metadata' => json_encode(['lunch_session_id' => $session->id]),
                'state' => [
                    'values' => [
                        'enseigne' => ['enseigne_id' => ['value' => null, 'selected_option' => ['value' => (string) $vendor->id]]],
                        'fulfillment' => ['fulfillment_type' => ['value' => null, 'selected_option' => ['value' => 'pickup']]],
                        'deadline' => ['deadline_time' => ['value' => '12:00']],
                        'note' => ['note' => ['value' => '']],
                        'help' => ['help_requested' => ['selected_options' => []]],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('vendor_proposals', [
            'lunch_session_id' => $session->id,
            'vendor_id' => $vendor->id,
            'fulfillment_type' => 'pickup',
        ]);
    }

    public function test_proposal_create_submission_validates_vendor(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_USER'],
            'view' => [
                'callback_id' => 'proposal_create',
                'private_metadata' => json_encode(['lunch_session_id' => $session->id]),
                'state' => [
                    'values' => [
                        'enseigne' => ['enseigne_id' => ['value' => null, 'selected_option' => ['value' => '99999']]],
                        'fulfillment' => ['fulfillment_type' => ['value' => null, 'selected_option' => ['value' => 'pickup']]],
                        'deadline' => ['deadline_time' => ['value' => '12:00']],
                        'note' => ['note' => ['value' => '']],
                        'help' => ['help_requested' => ['selected_options' => []]],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('errors', $data['response_action']);
        $this->assertArrayHasKey('enseigne', $data['errors']);
    }

    public function test_restaurant_propose_creates_vendor_and_proposal(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->blocks->shouldReceive('orderModal')
            ->once()
            ->andReturn(['type' => 'modal']);

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_USER'],
            'view' => [
                'callback_id' => 'restaurant_propose',
                'private_metadata' => json_encode(['lunch_session_id' => $session->id]),
                'state' => [
                    'values' => [
                        'name' => ['name' => ['value' => 'Nouveau Restaurant']],
                        'url_website' => ['url_website' => ['value' => '']],
                        'fulfillment_types' => ['fulfillment_types' => ['selected_options' => [['value' => 'pickup']]]],
                        'allow_individual' => ['allow_individual_order' => ['selected_options' => []]],
                        'deadline' => ['deadline_time' => ['value' => '12:00']],
                        'note' => ['note' => ['value' => '']],
                        'help' => ['help_requested' => ['selected_options' => []]],
                        'file' => ['file_upload' => ['files' => []]],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('vendors', [
            'name' => 'Nouveau Restaurant',
            'organization_id' => $this->organization->id,
        ]);
        $this->assertDatabaseHas('vendor_proposals', [
            'lunch_session_id' => $session->id,
        ]);
    }

    public function test_restaurant_propose_validates_name_required(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_USER'],
            'view' => [
                'callback_id' => 'restaurant_propose',
                'private_metadata' => json_encode(['lunch_session_id' => $session->id]),
                'state' => [
                    'values' => [
                        'name' => ['name' => ['value' => '']],
                        'url_website' => ['url_website' => ['value' => '']],
                        'fulfillment_types' => ['fulfillment_types' => ['selected_options' => [['value' => 'pickup']]]],
                        'allow_individual' => ['allow_individual_order' => ['selected_options' => []]],
                        'deadline' => ['deadline_time' => ['value' => '12:00']],
                        'note' => ['note' => ['value' => '']],
                        'help' => ['help_requested' => ['selected_options' => []]],
                        'file' => ['file_upload' => ['files' => []]],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('errors', $data['response_action']);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function test_order_create_validates_description_required(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_USER'],
            'view' => [
                'callback_id' => 'order_create',
                'private_metadata' => json_encode([
                    'proposal_id' => $proposal->id,
                    'lunch_session_id' => $session->id,
                ]),
                'state' => [
                    'values' => [
                        'description' => ['description' => ['value' => '']],
                        'price_estimated' => ['price_estimated' => ['value' => '10']],
                        'notes' => ['notes' => ['value' => '']],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('errors', $data['response_action']);
        $this->assertArrayHasKey('description', $data['errors']);
    }

    public function test_order_edit_submission_updates_order(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_USER',
            'description' => 'Old description',
        ]);

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_USER')
            ->andReturn(false);

        $this->messenger->shouldReceive('updateProposalMessage')->once();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with(Mockery::any(), 'U_USER', 'Commande mise a jour.', Mockery::any());

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_USER'],
            'view' => [
                'callback_id' => 'order_edit',
                'private_metadata' => json_encode([
                    'proposal_id' => $proposal->id,
                    'lunch_session_id' => $session->id,
                ]),
                'state' => [
                    'values' => [
                        'description' => ['description' => ['value' => 'Updated description']],
                        'price_estimated' => ['price_estimated' => ['value' => '15']],
                        'notes' => ['notes' => ['value' => '']],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'description' => 'Updated description',
        ]);
    }

    public function test_order_edit_rejects_on_closed_session(): void
    {
        $session = LunchSession::factory()->for($this->organization)->closed()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();
        Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_USER',
        ]);

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_USER', 'La journee est cloturee.');

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_USER'],
            'view' => [
                'callback_id' => 'order_edit',
                'private_metadata' => json_encode([
                    'proposal_id' => $proposal->id,
                    'lunch_session_id' => $session->id,
                ]),
                'state' => [
                    'values' => [
                        'description' => ['description' => ['value' => 'Updated']],
                        'price_estimated' => ['price_estimated' => ['value' => '15']],
                        'notes' => ['notes' => ['value' => '']],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_enseigne_create_creates_vendor(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with(Mockery::any(), 'U_USER', 'Enseigne ajoutee.', Mockery::any());

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_USER'],
            'view' => [
                'callback_id' => 'enseigne_create',
                'private_metadata' => json_encode(['lunch_session_id' => $session->id]),
                'state' => [
                    'values' => [
                        'name' => ['name' => ['value' => 'Pizza Express']],
                        'url_menu' => ['url_menu' => ['value' => '']],
                        'notes' => ['notes' => ['value' => '']],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('vendors', [
            'name' => 'Pizza Express',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_enseigne_update_updates_vendor(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create([
            'name' => 'Old Name',
        ]);
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_USER')
            ->andReturn(true);

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with(Mockery::any(), 'U_USER', 'Enseigne mise a jour.', Mockery::any());

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_USER'],
            'view' => [
                'callback_id' => 'enseigne_update',
                'private_metadata' => json_encode([
                    'vendor_id' => $vendor->id,
                    'lunch_session_id' => $session->id,
                ]),
                'state' => [
                    'values' => [
                        'name' => ['name' => ['value' => 'New Name']],
                        'cuisine_type' => ['cuisine_type' => ['value' => 'Italian']],
                        'url_website' => ['url_website' => ['value' => '']],
                        'url_menu' => ['url_menu' => ['value' => '']],
                        'fulfillment_types' => ['fulfillment_types' => ['selected_options' => [['value' => 'pickup']]]],
                        'allow_individual' => ['allow_individual_order' => ['selected_options' => []]],
                        'notes' => ['notes' => ['value' => '']],
                        'active' => ['active' => ['value' => null, 'selected_option' => ['value' => '1']]],
                        'file' => ['file_upload' => ['files' => []]],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'New Name',
        ]);
    }

    public function test_role_delegate_transfers_role(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_FROM')
            ->create();

        $this->messenger->shouldReceive('updateProposalMessage')->once();
        $this->messenger->shouldReceive('postRoleDelegation')->once();

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_FROM'],
            'view' => [
                'callback_id' => 'role_delegate',
                'private_metadata' => json_encode([
                    'proposal_id' => $proposal->id,
                    'role' => 'runner',
                ]),
                'state' => [
                    'values' => [
                        'delegate' => ['user_id' => ['selected_user' => 'U_TO']],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('vendor_proposals', [
            'id' => $proposal->id,
            'runner_user_id' => 'U_TO',
        ]);
    }

    public function test_adjust_price_updates_final_price(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_RUNNER')
            ->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'price_estimated' => 10.00,
            'price_final' => null,
        ]);

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_RUNNER')
            ->andReturn(false);

        $this->messenger->shouldReceive('updateProposalMessage')->once();

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with(Mockery::any(), 'U_RUNNER', 'Prix final mis a jour.', Mockery::any());

        $payload = [
            'type' => 'view_submission',
            'user' => ['id' => 'U_RUNNER'],
            'view' => [
                'callback_id' => 'order_adjust_price',
                'private_metadata' => json_encode([
                    'proposal_id' => $proposal->id,
                    'lunch_session_id' => $session->id,
                ]),
                'state' => [
                    'values' => [
                        'order' => ['order_id' => ['value' => null, 'selected_option' => ['value' => (string) $order->id]]],
                        'price_final' => ['price_final' => ['value' => '12,50']],
                    ],
                ],
            ],
        ];

        $response = $this->handler->handleInteractivity($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'price_final' => 12.50,
        ]);
    }

    public function test_session_close_via_session_action(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_ADMIN')
            ->andReturn(true);

        $this->messenger->shouldReceive('postClosureSummary')->once();

        $payload = $this->blockActionPayload('session.close', (string) $session->id, $session->provider_channel_id, 'U_ADMIN');

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('lunch_sessions', [
            'id' => $session->id,
            'status' => LunchSessionStatus::Closed->value,
        ]);
    }

    public function test_dashboard_close_session_allowed_for_orderer(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withOrderer('U_ORDERER')
            ->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_ORDERER')
            ->andReturn(false);

        $this->messenger->shouldReceive('postClosureSummary')->once();

        $payload = $this->blockActionPayload('dashboard_close_session', (string) $session->id, $session->provider_channel_id, 'U_ORDERER');

        $this->handler->handleInteractivity($payload);

        $this->assertDatabaseHas('lunch_sessions', [
            'id' => $session->id,
            'status' => LunchSessionStatus::Closed->value,
        ]);
    }

    public function test_open_adjust_price_modal_denied_for_non_runner(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->withRunner('U_RUNNER')
            ->create();

        $this->messenger->shouldReceive('isAdmin')
            ->with('U_OTHER')
            ->andReturn(false);

        $this->messenger->shouldReceive('postEphemeral')
            ->once()
            ->with($session->provider_channel_id, 'U_OTHER', 'Seul le runner/orderer peut ajuster les prix.');

        $payload = $this->blockActionPayload('open_adjust_price_modal', (string) $proposal->id, $session->provider_channel_id, 'U_OTHER');

        $this->handler->handleInteractivity($payload);
    }

    public function test_dashboard_view_orders_posts_summary(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        $this->messenger->shouldReceive('postSummary')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $proposal->id));

        $payload = $this->blockActionPayload('dashboard_view_orders', (string) $proposal->id, $session->provider_channel_id);

        $this->handler->handleInteractivity($payload);
    }

    private function blockActionPayload(string $actionId, string $value, string $channelId, string $userId = 'U_USER'): array
    {
        return [
            'type' => 'block_actions',
            'user' => ['id' => $userId],
            'channel' => ['id' => $channelId],
            'trigger_id' => 'trigger123',
            'actions' => [
                ['action_id' => $actionId, 'value' => $value],
            ],
        ];
    }

    private function invokeParsePrice(?string $value): ?float
    {
        $handler = $this->app->make(OrderInteractionHandler::class);
        $method = new ReflectionMethod(BaseInteractionHandler::class, 'parsePrice');
        $method->setAccessible(true);

        return $method->invoke($handler, $value);
    }
}
