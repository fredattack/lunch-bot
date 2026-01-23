<?php

namespace Tests\Unit\Services\Slack;

use App\Actions\LunchSession\CloseLunchSession;
use App\Actions\LunchSession\CreateLunchSession;
use App\Actions\Order\CreateOrder;
use App\Actions\Order\DeleteOrder;
use App\Actions\Order\UpdateOrder;
use App\Actions\Vendor\CreateVendor;
use App\Actions\Vendor\UpdateVendor;
use App\Actions\VendorProposal\AssignRole;
use App\Actions\VendorProposal\DelegateRole;
use App\Actions\VendorProposal\ProposeRestaurant;
use App\Actions\VendorProposal\ProposeVendor;
use App\Enums\LunchSessionStatus;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Vendor;
use App\Models\VendorProposal;
use App\Services\Slack\DashboardBlockBuilder;
use App\Services\Slack\DashboardStateResolver;
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

        $this->handler = new SlackInteractionHandler(
            $this->slack,
            $this->messenger,
            $this->blocks,
            $this->dashboardBlocks,
            new DashboardStateResolver($this->slack),
            app(CloseLunchSession::class),
            app(CreateLunchSession::class),
            app(ProposeVendor::class),
            app(ProposeRestaurant::class),
            app(AssignRole::class),
            app(DelegateRole::class),
            app(CreateOrder::class),
            app(UpdateOrder::class),
            app(DeleteOrder::class),
            app(CreateVendor::class),
            app(UpdateVendor::class)
        );
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        Mockery::close();
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

    private function invokeParsePrice(?string $value): ?float
    {
        $method = new ReflectionMethod(SlackInteractionHandler::class, 'parsePrice');
        $method->setAccessible(true);

        return $method->invoke($this->handler, $value);
    }
}
