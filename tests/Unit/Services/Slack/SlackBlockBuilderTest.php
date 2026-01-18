<?php

namespace Tests\Unit\Services\Slack;

use App\Enums\SlackAction;
use App\Models\Organization;
use App\Models\Vendor;
use App\Models\VendorProposal;
use App\Services\Slack\SlackBlockBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlackBlockBuilderTest extends TestCase
{
    use RefreshDatabase;

    private SlackBlockBuilder $builder;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);

        $this->builder = new SlackBlockBuilder;
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_proposal_manage_modal_shows_vendor_info(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['name' => 'Sushi Wasabi']);
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->pickup()
            ->create();

        $modal = $this->builder->proposalManageModal($proposal, 'U_USER');

        $this->assertEquals('modal', $modal['type']);
        $this->assertEquals(SlackAction::CallbackProposalManage->value, $modal['callback_id']);
        $this->assertStringContainsString('Sushi Wasabi', $modal['blocks'][0]['text']['text']);
    }

    public function test_proposal_manage_modal_shows_runner_label_for_pickup(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->pickup()
            ->create();

        $modal = $this->builder->proposalManageModal($proposal, 'U_USER');

        $this->assertStringContainsString('Runner', $modal['blocks'][0]['text']['text']);
    }

    public function test_proposal_manage_modal_shows_orderer_label_for_delivery(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->delivery()
            ->create();

        $modal = $this->builder->proposalManageModal($proposal, 'U_USER');

        $this->assertStringContainsString('Orderer', $modal['blocks'][0]['text']['text']);
    }

    public function test_proposal_manage_modal_shows_take_charge_button_when_no_responsible(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->pickup()
            ->create([
                'runner_user_id' => null,
                'orderer_user_id' => null,
            ]);

        $modal = $this->builder->proposalManageModal($proposal, 'U_USER');

        $actionsBlock = collect($modal['blocks'])->firstWhere('type', 'actions');
        $this->assertNotNull($actionsBlock);
        $this->assertEquals(SlackAction::ProposalTakeCharge->value, $actionsBlock['elements'][0]['action_id']);
        $this->assertStringContainsString('aller chercher', $actionsBlock['elements'][0]['text']['text']);
    }

    public function test_proposal_manage_modal_shows_delivery_button_text_for_delivery(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->delivery()
            ->create([
                'runner_user_id' => null,
                'orderer_user_id' => null,
            ]);

        $modal = $this->builder->proposalManageModal($proposal, 'U_USER');

        $actionsBlock = collect($modal['blocks'])->firstWhere('type', 'actions');
        $this->assertNotNull($actionsBlock);
        $this->assertStringContainsString('passer la commande', $actionsBlock['elements'][0]['text']['text']);
    }

    public function test_proposal_manage_modal_shows_context_when_already_assigned(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->pickup()
            ->withRunner('U_OTHER')
            ->create();

        $modal = $this->builder->proposalManageModal($proposal, 'U_USER');

        $contextBlock = collect($modal['blocks'])->firstWhere('type', 'context');
        $this->assertNotNull($contextBlock);
        $this->assertStringContainsString('responsable est deja assigne', $contextBlock['elements'][0]['text']);
    }

    public function test_proposal_manage_modal_shows_context_when_user_already_in_charge(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->pickup()
            ->withRunner('U_USER')
            ->create();

        $modal = $this->builder->proposalManageModal($proposal, 'U_USER');

        $contextBlock = collect($modal['blocks'])->firstWhere('type', 'context');
        $this->assertNotNull($contextBlock);
        $this->assertStringContainsString('deja en charge', $contextBlock['elements'][0]['text']);
    }

    public function test_proposal_manage_modal_contains_metadata(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->create();

        $modal = $this->builder->proposalManageModal($proposal, 'U_USER');

        $metadata = json_decode($modal['private_metadata'], true);
        $this->assertEquals($proposal->id, $metadata['proposal_id']);
        $this->assertEquals($proposal->lunch_session_id, $metadata['lunch_session_id']);
    }

    public function test_order_modal_shows_delete_button_in_edit_mode(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->create();
        $order = \App\Models\Order::factory()
            ->for($proposal)
            ->create(['organization_id' => $this->organization->id]);

        $modal = $this->builder->orderModal($proposal, $order, false, true);

        $dangerZone = collect($modal['blocks'])->firstWhere('block_id', 'danger_zone');
        $this->assertNotNull($dangerZone);
        $this->assertEquals(SlackAction::OrderDelete->value, $dangerZone['elements'][0]['action_id']);
        $this->assertEquals('danger', $dangerZone['elements'][0]['style']);
        $this->assertArrayHasKey('confirm', $dangerZone['elements'][0]);
    }

    public function test_order_modal_hides_delete_button_in_create_mode(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->create();

        $modal = $this->builder->orderModal($proposal, null, false, false);

        $dangerZone = collect($modal['blocks'])->firstWhere('block_id', 'danger_zone');
        $this->assertNull($dangerZone);
    }

    public function test_error_modal_shows_title_and_message(): void
    {
        $modal = $this->builder->errorModal('Erreur', 'Une erreur est survenue.');

        $this->assertEquals('modal', $modal['type']);
        $this->assertEquals('Erreur', $modal['title']['text']);
        $this->assertEquals('Fermer', $modal['close']['text']);
        $this->assertCount(2, $modal['blocks']);
        $this->assertStringContainsString(':warning:', $modal['blocks'][0]['text']['text']);
        $this->assertStringContainsString('Erreur', $modal['blocks'][0]['text']['text']);
        $this->assertStringContainsString('Une erreur est survenue.', $modal['blocks'][1]['text']['text']);
    }

    public function test_error_modal_truncates_long_title(): void
    {
        $modal = $this->builder->errorModal('This is a very long error title that exceeds limit', 'Message');

        $this->assertLessThanOrEqual(24, mb_strlen($modal['title']['text']));
    }
}
