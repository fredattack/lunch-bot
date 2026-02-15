<?php

namespace Tests\Unit\Services\Slack;

use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Vendor;
use App\Models\VendorProposal;
use App\Services\Slack\SlackBlockBuilder;
use App\Services\Slack\SlackMessenger;
use App\Services\Slack\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SlackMessengerTest extends TestCase
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

    private SlackMessenger $messenger;

    private MockInterface $slack;

    private MockInterface $blocks;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);

        $this->slack = Mockery::mock(SlackService::class);
        $this->blocks = Mockery::mock(SlackBlockBuilder::class);

        $this->messenger = new SlackMessenger($this->slack, $this->blocks);
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_post_daily_kickoff_posts_message_and_saves_ts(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => null]);

        $this->blocks->shouldReceive('dailyKickoffBlocks')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $session->id))
            ->andReturn([['type' => 'section']]);

        $this->slack->shouldReceive('postMessage')
            ->once()
            ->with(
                $session->provider_channel_id,
                Mockery::type('string'),
                [['type' => 'section']]
            )
            ->andReturn(['ok' => true, 'ts' => '1111.2222']);

        $this->messenger->postDailyKickoff($session);

        $this->assertEquals('1111.2222', $session->fresh()->provider_message_ts);
    }

    public function test_post_daily_kickoff_skips_when_ts_already_set(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => '9999.0000']);

        $this->slack->shouldNotReceive('postMessage');
        $this->blocks->shouldNotReceive('dailyKickoffBlocks');

        $this->messenger->postDailyKickoff($session);

        $this->assertEquals('9999.0000', $session->fresh()->provider_message_ts);
    }

    public function test_post_daily_kickoff_handles_api_failure(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => null]);

        $this->blocks->shouldReceive('dailyKickoffBlocks')
            ->andReturn([['type' => 'section']]);

        $this->slack->shouldReceive('postMessage')
            ->andReturn(['ok' => false, 'error' => 'channel_not_found']);

        $this->messenger->postDailyKickoff($session);

        $this->assertNull($session->fresh()->provider_message_ts);
    }

    public function test_post_order_created_message_posts_with_vendor_info(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['name' => 'Sushi Palace']);
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->for($vendor)
            ->pickup()
            ->create(['provider_message_ts' => null]);

        $this->slack->shouldReceive('postMessage')
            ->once()
            ->with(
                $session->provider_channel_id,
                Mockery::on(fn ($text) => str_contains($text, 'Sushi Palace')),
                Mockery::type('array'),
                $session->provider_message_ts
            )
            ->andReturn(['ok' => true, 'ts' => '3333.4444']);

        $this->messenger->postOrderCreatedMessage($proposal, 'U_CREATOR');

        $this->assertEquals('3333.4444', $proposal->fresh()->provider_message_ts);
    }

    public function test_post_order_created_message_includes_help_warning(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->for($vendor)
            ->pickup()
            ->create([
                'help_requested' => true,
                'provider_message_ts' => null,
            ]);

        $this->slack->shouldReceive('postMessage')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($blocks) {
                    $text = $blocks[0]['text']['text'] ?? '';

                    return str_contains($text, ':warning:') && str_contains($text, 'Aide demandee');
                }),
                Mockery::any()
            )
            ->andReturn(['ok' => true, 'ts' => '5555.6666']);

        $this->messenger->postOrderCreatedMessage($proposal, 'U_CREATOR');
    }

    public function test_post_order_created_message_includes_note(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->for($vendor)
            ->pickup()
            ->create([
                'note' => 'Pas de sauce piquante svp',
                'provider_message_ts' => null,
            ]);

        $this->slack->shouldReceive('postMessage')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($blocks) {
                    $text = $blocks[0]['text']['text'] ?? '';

                    return str_contains($text, 'Pas de sauce piquante svp');
                }),
                Mockery::any()
            )
            ->andReturn(['ok' => true, 'ts' => '7777.8888']);

        $this->messenger->postOrderCreatedMessage($proposal, 'U_CREATOR');
    }

    public function test_post_order_created_message_hides_other_vendor_button(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->for($vendor)
            ->pickup()
            ->create(['provider_message_ts' => null]);

        $this->slack->shouldReceive('postMessage')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($blocks) {
                    $actionsBlock = collect($blocks)->firstWhere('type', 'actions');

                    return count($actionsBlock['elements']) === 1;
                }),
                Mockery::any()
            )
            ->andReturn(['ok' => true, 'ts' => '1111.0000']);

        $this->messenger->postOrderCreatedMessage($proposal, 'U_CREATOR', true);
    }

    public function test_post_order_created_message_saves_ts_on_success(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->for($vendor)
            ->pickup()
            ->create(['provider_message_ts' => null]);

        $this->slack->shouldReceive('postMessage')
            ->andReturn(['ok' => true, 'ts' => 'saved_ts_value']);

        $this->messenger->postOrderCreatedMessage($proposal, 'U_CREATOR');

        $this->assertEquals('saved_ts_value', $proposal->fresh()->provider_message_ts);
    }

    public function test_update_proposal_message_updates_existing(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['name' => 'Pizza Roma']);
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->for($vendor)
            ->create(['provider_message_ts' => '1234.5678']);

        $this->blocks->shouldReceive('proposalBlocks')
            ->once()
            ->andReturn([['type' => 'section']]);

        $this->slack->shouldReceive('updateMessage')
            ->once()
            ->with(
                $session->provider_channel_id,
                '1234.5678',
                Mockery::on(fn ($text) => str_contains($text, 'Pizza Roma')),
                [['type' => 'section']]
            );

        $this->messenger->updateProposalMessage($proposal);
    }

    public function test_update_proposal_message_skips_when_no_ts(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->create(['provider_message_ts' => null]);

        $this->slack->shouldNotReceive('updateMessage');
        $this->blocks->shouldNotReceive('proposalBlocks');

        $this->messenger->updateProposalMessage($proposal);
    }

    public function test_post_summary_calculates_totals(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'price_estimated' => 10.00,
            'price_final' => 12.00,
        ]);
        Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'price_estimated' => 8.50,
            'price_final' => null,
        ]);

        $this->blocks->shouldReceive('summaryBlocks')
            ->once()
            ->with(
                Mockery::on(fn ($p) => $p->id === $proposal->id),
                Mockery::type('array'),
                Mockery::on(function ($totals) {
                    return $totals['estimated'] === '18.50'
                        && $totals['final'] === '20.50';
                })
            )
            ->andReturn([['type' => 'section']]);

        $this->slack->shouldReceive('postMessage')
            ->once()
            ->with(
                $session->provider_channel_id,
                'Recapitulatif',
                [['type' => 'section']],
                $session->provider_message_ts
            );

        $this->messenger->postSummary($proposal);
    }

    public function test_post_closure_summary_aggregates_by_user(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => '1234.5678']);

        $proposal1 = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();
        $proposal2 = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        Order::factory()->for($proposal1)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_ALICE',
            'price_estimated' => 10.00,
            'price_final' => 12.00,
        ]);
        Order::factory()->for($proposal2)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_ALICE',
            'price_estimated' => 5.00,
            'price_final' => null,
        ]);
        Order::factory()->for($proposal1)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_BOB',
            'price_estimated' => 15.00,
            'price_final' => 14.50,
        ]);

        $this->slack->shouldReceive('postMessage')
            ->once()
            ->with(
                $session->provider_channel_id,
                'Journee cloturee',
                Mockery::on(function ($blocks) {
                    $text = $blocks[0]['text']['text'] ?? '';

                    return str_contains($text, 'U_ALICE')
                        && str_contains($text, 'U_BOB')
                        && str_contains($text, 'cloturee');
                }),
                '1234.5678'
            );

        $this->messenger->postClosureSummary($session);
    }

    public function test_post_closure_summary_handles_empty_orders(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => '1234.5678']);

        $this->slack->shouldReceive('postMessage')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($blocks) {
                    $text = $blocks[0]['text']['text'] ?? '';

                    return str_contains($text, 'Aucun total');
                }),
                Mockery::any()
            );

        $this->messenger->postClosureSummary($session);
    }

    public function test_notify_sessions_locked_posts_to_each_session(): void
    {
        $session1 = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => '1111.0000']);
        $session2 = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => '2222.0000']);

        $this->slack->shouldReceive('postMessage')
            ->twice();

        $this->messenger->notifySessionsLocked(new Collection([$session1, $session2]));
    }

    public function test_notify_sessions_locked_skips_sessions_without_ts(): void
    {
        $session1 = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => '1111.0000']);
        $session2 = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => null]);

        $this->slack->shouldReceive('postMessage')
            ->once()
            ->with(
                $session1->provider_channel_id,
                Mockery::any(),
                Mockery::any(),
                '1111.0000'
            );

        $this->messenger->notifySessionsLocked(new Collection([$session1, $session2]));
    }

    public function test_post_role_delegation_formats_message(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => '5555.0000']);
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        $this->slack->shouldReceive('postMessage')
            ->once()
            ->with(
                $session->provider_channel_id,
                'Role delegue',
                Mockery::on(function ($blocks) {
                    $text = $blocks[0]['text']['text'] ?? '';

                    return str_contains($text, 'runner')
                        && str_contains($text, 'U_FROM')
                        && str_contains($text, 'U_TO');
                }),
                '5555.0000'
            );

        $this->messenger->postRoleDelegation($proposal, 'runner', 'U_FROM', 'U_TO');
    }

    public function test_post_role_delegation_skips_when_no_session_ts(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => null]);
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($session)
            ->create();

        $this->slack->shouldNotReceive('postMessage');

        $this->messenger->postRoleDelegation($proposal, 'runner', 'U_FROM', 'U_TO');
    }

    public function test_closure_summary_skips_when_no_session_ts(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['provider_message_ts' => null]);

        $this->slack->shouldNotReceive('postMessage');

        $this->messenger->postClosureSummary($session);
    }
}
