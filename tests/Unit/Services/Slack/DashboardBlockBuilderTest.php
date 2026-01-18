<?php

namespace Tests\Unit\Services\Slack;

use App\Enums\DashboardState;
use App\Enums\ProposalStatus;
use App\Enums\SlackAction;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\VendorProposal;
use App\Services\Slack\DashboardBlockBuilder;
use App\Services\Slack\DashboardStateResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBlockBuilderTest extends TestCase
{
    use RefreshDatabase;

    private DashboardBlockBuilder $builder;

    private DashboardStateResolver $resolver;

    private string $userId = 'U_TEST_USER';

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new DashboardBlockBuilder;
        $this->resolver = new DashboardStateResolver;
    }

    public function test_s1_modal_shows_start_actions(): void
    {
        $session = $this->createTodaySession();
        $context = $this->resolver->resolve($session, $this->userId);

        $modal = $this->builder->buildModal($context);

        $this->assertEquals('modal', $modal['type']);
        $this->assertEquals(SlackAction::CallbackLunchDashboard->value, $modal['callback_id']);
        $this->assertStringContainsString('Lunch', $modal['title']['text']);

        $blocks = $modal['blocks'];
        $this->assertBlockContainsText($blocks, "Aucune commande n'a ete lancee");
        $this->assertBlockContainsAction($blocks, SlackAction::DashboardStartFromCatalog->value);
        $this->assertBlockContainsAction($blocks, SlackAction::DashboardCreateProposal->value);
    }

    public function test_s2_modal_shows_proposal_cards_and_join_actions(): void
    {
        $session = $this->createTodaySession();
        VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Open]);
        VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Open]);

        $context = $this->resolver->resolve($session, $this->userId);

        $modal = $this->builder->buildModal($context);
        $blocks = $modal['blocks'];

        $this->assertBlockContainsAction($blocks, SlackAction::DashboardJoinProposal->value);
        $this->assertBlockContainsAction($blocks, SlackAction::DashboardStartFromCatalog->value);
    }

    public function test_s3_modal_shows_my_order_block(): void
    {
        $session = $this->createTodaySession();
        $proposal = VendorProposal::factory()->for($session)->create([
            'status' => ProposalStatus::Open,
            'runner_user_id' => 'U_OTHER_RUNNER',
        ]);
        Order::factory()->for($proposal)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => $this->userId,
            'description' => 'Mon burger',
            'price_estimated' => 12.50,
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $modal = $this->builder->buildModal($context);
        $blocks = $modal['blocks'];

        $this->assertBlockContainsText($blocks, 'Ma commande');
        $this->assertBlockContainsAction($blocks, SlackAction::OrderOpenEdit->value);
    }

    public function test_s4_modal_shows_in_charge_block_with_recap_and_close(): void
    {
        $session = $this->createTodaySession();
        VendorProposal::factory()->for($session)->create([
            'status' => ProposalStatus::Ordering,
            'runner_user_id' => $this->userId,
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $modal = $this->builder->buildModal($context);
        $blocks = $modal['blocks'];

        $this->assertBlockContainsText($blocks, 'prise en charge par vous');
        $this->assertBlockContainsAction($blocks, SlackAction::ProposalOpenRecap->value);
        $this->assertBlockContainsAction($blocks, SlackAction::ProposalClose->value);
    }

    public function test_s5_modal_shows_relaunch_action(): void
    {
        $session = $this->createTodaySession();
        VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Closed]);

        $context = $this->resolver->resolve($session, $this->userId);

        $modal = $this->builder->buildModal($context);
        $blocks = $modal['blocks'];

        $this->assertBlockContainsText($blocks, 'Aucune commande en cours');
        $this->assertBlockContainsAction($blocks, SlackAction::DashboardRelaunch->value);
    }

    public function test_s6_modal_shows_history_header(): void
    {
        $session = LunchSession::factory()->create([
            'date' => Carbon::now(config('lunch.timezone', 'Europe/Paris'))->subDay(),
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $modal = $this->builder->buildModal($context);
        $blocks = $modal['blocks'];

        $this->assertBlockContainsText($blocks, 'historique');
    }

    public function test_s6_modal_shows_no_actions_for_regular_user(): void
    {
        $session = LunchSession::factory()->create([
            'date' => Carbon::now(config('lunch.timezone', 'Europe/Paris'))->subDay(),
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $modal = $this->builder->buildModal($context);
        $blocks = $modal['blocks'];

        $this->assertBlockDoesNotContainAction($blocks, SlackAction::DashboardJoinProposal->value);
        $this->assertBlockDoesNotContainAction($blocks, SlackAction::DashboardStartFromCatalog->value);
        $this->assertBlockDoesNotContainAction($blocks, SlackAction::OrderOpenEdit->value);
    }

    public function test_private_metadata_is_valid_json(): void
    {
        $session = $this->createTodaySession();
        $context = $this->resolver->resolve($session, $this->userId);

        $modal = $this->builder->buildModal($context);

        $metadata = json_decode($modal['private_metadata'], true);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('state', $metadata);
        $this->assertEquals(DashboardState::NoProposal->value, $metadata['state']);
    }

    public function test_proposal_card_shows_participants(): void
    {
        $session = $this->createTodaySession();
        $proposal = VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Open]);
        Order::factory()->for($proposal)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => 'U_PARTICIPANT_1',
        ]);
        Order::factory()->for($proposal)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => 'U_PARTICIPANT_2',
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $modal = $this->builder->buildModal($context);
        $blocks = $modal['blocks'];

        $blocksJson = json_encode($blocks);
        $this->assertStringContainsString('U_PARTICIPANT_1', $blocksJson);
        $this->assertStringContainsString('U_PARTICIPANT_2', $blocksJson);
    }

    private function createTodaySession(): LunchSession
    {
        return LunchSession::factory()->open()->create([
            'date' => Carbon::now(config('lunch.timezone', 'Europe/Paris'))->toDateString(),
        ]);
    }

    private function assertBlockContainsText(array $blocks, string $text): void
    {
        $blocksJson = json_encode($blocks);
        $this->assertStringContainsString($text, $blocksJson, "Blocks should contain text: {$text}");
    }

    private function assertBlockContainsAction(array $blocks, string $actionId): void
    {
        $blocksJson = json_encode($blocks);
        $this->assertStringContainsString($actionId, $blocksJson, "Blocks should contain action_id: {$actionId}");
    }

    private function assertBlockDoesNotContainAction(array $blocks, string $actionId): void
    {
        $blocksJson = json_encode($blocks);
        $this->assertStringNotContainsString($actionId, $blocksJson, "Blocks should not contain action_id: {$actionId}");
    }
}
