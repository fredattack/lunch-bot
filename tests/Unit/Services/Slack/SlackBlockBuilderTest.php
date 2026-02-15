<?php

namespace Tests\Unit\Services\Slack;

use App\Enums\FulfillmentType;
use App\Enums\SlackAction;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Vendor;
use App\Models\VendorProposal;
use App\Services\Slack\SlackBlockBuilder;
use Carbon\Carbon;
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
        $headerContext = collect($modal['blocks'])->firstWhere('type', 'context');
        $vendorNameElement = collect($headerContext['elements'])->firstWhere('type', 'mrkdwn');
        $this->assertStringContainsString('Sushi Wasabi', $vendorNameElement['text']);
    }

    public function test_proposal_manage_modal_shows_runner_label_for_pickup(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->pickup()
            ->create();

        $modal = $this->builder->proposalManageModal($proposal, 'U_USER');

        $sectionBlock = collect($modal['blocks'])->firstWhere('type', 'section');
        $this->assertStringContainsString('Runner', $sectionBlock['text']['text']);
    }

    public function test_proposal_manage_modal_shows_orderer_label_for_delivery(): void
    {
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->delivery()
            ->create();

        $modal = $this->builder->proposalManageModal($proposal, 'U_USER');

        $sectionBlock = collect($modal['blocks'])->firstWhere('type', 'section');
        $this->assertStringContainsString('Orderer', $sectionBlock['text']['text']);
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

        $contextBlock = collect($modal['blocks'])->where('type', 'context')->last();
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

        $contextBlock = collect($modal['blocks'])->where('type', 'context')->last();
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

    // --- dailyKickoffBlocks ---

    public function test_daily_kickoff_includes_date_header(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create([
                'date' => Carbon::parse('2025-06-15'),
                'deadline_at' => Carbon::parse('2025-06-15 11:30:00'),
            ]);

        $blocks = $this->builder->dailyKickoffBlocks($session);

        $sectionText = $blocks[0]['text']['text'] ?? '';
        $this->assertStringContainsString('2025-06-15', $sectionText);
        $this->assertStringContainsString('Dejeuner', $sectionText);
    }

    public function test_daily_kickoff_includes_deadline_info(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['deadline_at' => Carbon::parse('2025-06-15 11:30:00')]);

        $blocks = $this->builder->dailyKickoffBlocks($session);

        $sectionText = $blocks[0]['text']['text'] ?? '';
        $this->assertStringContainsString('Deadline', $sectionText);
        $this->assertStringContainsString('11:30', $sectionText);
    }

    public function test_daily_kickoff_includes_action_buttons(): void
    {
        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['deadline_at' => Carbon::parse('2025-06-15 11:30:00')]);

        $blocks = $this->builder->dailyKickoffBlocks($session);

        $actionsBlock = collect($blocks)->firstWhere('type', 'actions');
        $this->assertNotNull($actionsBlock);
        $this->assertCount(3, $actionsBlock['elements']);

        $actionIds = array_column($actionsBlock['elements'], 'action_id');
        $this->assertContains(SlackAction::OpenProposalModal->value, $actionIds);
        $this->assertContains(SlackAction::OpenAddEnseigneModal->value, $actionIds);
        $this->assertContains(SlackAction::CloseDay->value, $actionIds);
    }

    // --- proposalBlocks ---

    public function test_proposal_blocks_includes_vendor_name(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['name' => 'Thai Express']);
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->pickup()
            ->create();

        $blocks = $this->builder->proposalBlocks($proposal, 3);

        $contextBlock = collect($blocks)->firstWhere('type', 'context');
        $mrkdwnElement = collect($contextBlock['elements'])->firstWhere('type', 'mrkdwn');
        $this->assertStringContainsString('Thai Express', $mrkdwnElement['text']);
    }

    public function test_proposal_blocks_shows_fulfillment_type_label(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->delivery()
            ->create();

        $blocks = $this->builder->proposalBlocks($proposal, 0);

        $sectionBlock = collect($blocks)->firstWhere('type', 'section');
        $this->assertStringContainsString('Livraison', $sectionBlock['text']['text']);
    }

    public function test_proposal_blocks_shows_runner_info(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->pickup()
            ->withRunner('U_JOHN')
            ->create();

        $blocks = $this->builder->proposalBlocks($proposal, 0);

        $sectionBlock = collect($blocks)->firstWhere('type', 'section');
        $this->assertStringContainsString('<@U_JOHN>', $sectionBlock['text']['text']);
        $this->assertStringContainsString('Runner', $sectionBlock['text']['text']);
    }

    public function test_proposal_blocks_shows_orderer_info(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->pickup()
            ->withOrderer('U_JANE')
            ->create();

        $blocks = $this->builder->proposalBlocks($proposal, 0);

        $sectionBlock = collect($blocks)->firstWhere('type', 'section');
        $this->assertStringContainsString('<@U_JANE>', $sectionBlock['text']['text']);
    }

    public function test_proposal_blocks_includes_order_count(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->create();

        $blocks = $this->builder->proposalBlocks($proposal, 5);

        $contextBlocks = collect($blocks)->where('type', 'context');
        $orderCountBlock = $contextBlocks->first(function ($block) {
            $text = $block['elements'][0]['text'] ?? '';

            return str_contains($text, 'Commandes');
        });
        $this->assertNotNull($orderCountBlock);
        $this->assertStringContainsString('5', $orderCountBlock['elements'][0]['text']);
    }

    public function test_proposal_blocks_includes_action_buttons(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->create();

        $blocks = $this->builder->proposalBlocks($proposal, 0);

        $actionsBlocks = collect($blocks)->where('type', 'actions');
        $this->assertGreaterThanOrEqual(2, $actionsBlocks->count());

        $allActionIds = $actionsBlocks->flatMap(fn ($b) => collect($b['elements'])->pluck('action_id'));
        $this->assertTrue($allActionIds->contains(SlackAction::ClaimRunner->value));
        $this->assertTrue($allActionIds->contains(SlackAction::OpenOrderModal->value));
        $this->assertTrue($allActionIds->contains(SlackAction::OpenDelegateModal->value));
    }

    // --- summaryBlocks ---

    public function test_summary_blocks_lists_all_orders(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $order1 = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_ALICE',
            'description' => 'Ramen',
            'price_estimated' => 12.00,
        ]);
        $order2 = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_BOB',
            'description' => 'Sashimi',
            'price_estimated' => 15.00,
        ]);

        $blocks = $this->builder->summaryBlocks($proposal, [$order1, $order2], [
            'estimated' => '27.00',
            'final' => '27.00',
        ]);

        $text = $blocks[0]['text']['text'];
        $this->assertStringContainsString('U_ALICE', $text);
        $this->assertStringContainsString('U_BOB', $text);
        $this->assertStringContainsString('Ramen', $text);
        $this->assertStringContainsString('Sashimi', $text);
    }

    public function test_summary_blocks_shows_price_totals(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();

        $blocks = $this->builder->summaryBlocks($proposal, [], [
            'estimated' => '42.00',
            'final' => '45.50',
        ]);

        $text = $blocks[0]['text']['text'];
        $this->assertStringContainsString('42.00', $text);
        $this->assertStringContainsString('45.50', $text);
    }

    public function test_summary_blocks_handles_empty_orders(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();

        $blocks = $this->builder->summaryBlocks($proposal, [], [
            'estimated' => '0.00',
            'final' => '0.00',
        ]);

        $text = $blocks[0]['text']['text'];
        $this->assertStringContainsString('Aucune commande', $text);
    }

    // --- vendorsListModal ---

    public function test_vendors_list_modal_includes_search_input(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $modal = $this->builder->vendorsListModal($session, []);

        $searchBlock = collect($modal['blocks'])->firstWhere('block_id', 'search');
        $this->assertNotNull($searchBlock);
        $this->assertEquals('input', $searchBlock['type']);
        $this->assertEquals(SlackAction::VendorsListSearch->value, $searchBlock['element']['action_id']);
    }

    public function test_vendors_list_modal_lists_active_vendors(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $vendor1 = Vendor::factory()->for($this->organization)->create(['name' => 'Pizza Roma']);
        $vendor2 = Vendor::factory()->for($this->organization)->create(['name' => 'Sushi Place']);

        $modal = $this->builder->vendorsListModal($session, [$vendor1, $vendor2]);

        $sectionBlocks = collect($modal['blocks'])->where('type', 'section');
        $texts = $sectionBlocks->map(fn ($b) => $b['text']['text'] ?? '')->join(' ');
        $this->assertStringContainsString('Pizza Roma', $texts);
        $this->assertStringContainsString('Sushi Place', $texts);
    }

    public function test_vendors_list_modal_shows_empty_state(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $modal = $this->builder->vendorsListModal($session, []);

        $sectionBlocks = collect($modal['blocks'])->where('type', 'section');
        $emptyState = $sectionBlocks->first(fn ($b) => str_contains($b['text']['text'] ?? '', 'Aucun restaurant'));
        $this->assertNotNull($emptyState);
    }

    public function test_vendors_list_modal_includes_edit_buttons(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $vendor = Vendor::factory()->for($this->organization)->create();

        $modal = $this->builder->vendorsListModal($session, [$vendor]);

        $vendorSection = collect($modal['blocks'])->firstWhere('block_id', "vendor_{$vendor->id}");
        $this->assertNotNull($vendorSection);
        $this->assertEquals(SlackAction::VendorsListEdit->value, $vendorSection['accessory']['action_id']);
    }

    // --- recapModal ---

    public function test_recap_modal_shows_vendor_header(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['name' => 'Burger King']);
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->create();

        $modal = $this->builder->recapModal($proposal, [], ['estimated' => '0.00', 'final' => '0.00']);

        $this->assertEquals('Recapitulatif', $modal['title']['text']);
        $contextBlock = collect($modal['blocks'])->firstWhere('type', 'context');
        $mrkdwn = collect($contextBlock['elements'])->firstWhere('type', 'mrkdwn');
        $this->assertStringContainsString('Burger King', $mrkdwn['text']);
    }

    public function test_recap_modal_lists_orders_with_prices(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_ALICE',
            'description' => 'Big Mac',
            'price_estimated' => 9.50,
            'price_final' => 10.00,
        ]);

        $modal = $this->builder->recapModal($proposal, [$order], ['estimated' => '9.50', 'final' => '10.00']);

        $orderSection = collect($modal['blocks'])->first(fn ($b) => ($b['type'] ?? '') === 'section' && str_contains($b['text']['text'] ?? '', 'U_ALICE'));
        $this->assertNotNull($orderSection);
        $this->assertStringContainsString('Big Mac', $orderSection['text']['text']);
    }

    public function test_recap_modal_shows_totals_section(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->create();

        $modal = $this->builder->recapModal($proposal, [], ['estimated' => '25.00', 'final' => '30.00']);

        $blocks = $modal['blocks'];
        $totalsBlock = collect($blocks)->last();
        $this->assertStringContainsString('25.00', $totalsBlock['text']['text']);
        $this->assertStringContainsString('30.00', $totalsBlock['text']['text']);
    }

    // --- proposalModal ---

    public function test_proposal_modal_includes_vendor_select(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $vendor = Vendor::factory()->for($this->organization)->create(['name' => 'Test Vendor']);

        $modal = $this->builder->proposalModal($session, [$vendor]);

        $this->assertEquals(SlackAction::CallbackProposalCreate->value, $modal['callback_id']);
        $enseigneBlock = collect($modal['blocks'])->firstWhere('block_id', 'enseigne');
        $this->assertNotNull($enseigneBlock);
        $this->assertEquals('static_select', $enseigneBlock['element']['type']);
        $this->assertEquals((string) $vendor->id, $enseigneBlock['element']['options'][0]['value']);
    }

    public function test_proposal_modal_includes_fulfillment_select(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $vendor = Vendor::factory()->for($this->organization)->create();

        $modal = $this->builder->proposalModal($session, [$vendor]);

        $fulfillmentBlock = collect($modal['blocks'])->firstWhere('block_id', 'fulfillment');
        $this->assertNotNull($fulfillmentBlock);
        $options = $fulfillmentBlock['element']['options'];
        $values = array_column($options, 'value');
        $this->assertContains(FulfillmentType::Pickup->value, $values);
        $this->assertContains(FulfillmentType::Delivery->value, $values);
    }

    public function test_proposal_modal_includes_deadline_field(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $vendor = Vendor::factory()->for($this->organization)->create();

        $modal = $this->builder->proposalModal($session, [$vendor]);

        $deadlineBlock = collect($modal['blocks'])->firstWhere('block_id', 'deadline');
        $this->assertNotNull($deadlineBlock);
        $this->assertEquals('11:30', $deadlineBlock['element']['initial_value']);
    }

    public function test_proposal_modal_includes_note_and_help_fields(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $vendor = Vendor::factory()->for($this->organization)->create();

        $modal = $this->builder->proposalModal($session, [$vendor]);

        $noteBlock = collect($modal['blocks'])->firstWhere('block_id', 'note');
        $helpBlock = collect($modal['blocks'])->firstWhere('block_id', 'help');
        $this->assertNotNull($noteBlock);
        $this->assertNotNull($helpBlock);
        $this->assertEquals('checkboxes', $helpBlock['element']['type']);
    }

    public function test_proposal_modal_includes_new_restaurant_button(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $vendor = Vendor::factory()->for($this->organization)->create();

        $modal = $this->builder->proposalModal($session, [$vendor]);

        $newRestaurantBlock = collect($modal['blocks'])->firstWhere('block_id', 'new_restaurant_action');
        $this->assertNotNull($newRestaurantBlock);
        $this->assertEquals(SlackAction::DashboardCreateProposal->value, $newRestaurantBlock['elements'][0]['action_id']);
    }

    // --- addVendorModal / editVendorModal ---

    public function test_add_vendor_modal_has_correct_structure(): void
    {
        $modal = $this->builder->addVendorModal();

        $this->assertEquals('modal', $modal['type']);
        $this->assertEquals(SlackAction::CallbackEnseigneCreate->value, $modal['callback_id']);
        $this->assertEquals('Ajouter une enseigne', $modal['title']['text']);
        $this->assertArrayNotHasKey('private_metadata', $modal);
    }

    public function test_add_vendor_modal_includes_metadata(): void
    {
        $modal = $this->builder->addVendorModal(['lunch_session_id' => 42]);

        $metadata = json_decode($modal['private_metadata'], true);
        $this->assertEquals(42, $metadata['lunch_session_id']);
    }

    public function test_edit_vendor_modal_pre_fills_existing_data(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create([
            'name' => 'Existing Vendor',
            'cuisine_type' => 'Italian',
            'url_website' => 'https://example.com',
        ]);

        $modal = $this->builder->editVendorModal($vendor);

        $this->assertEquals(SlackAction::CallbackEnseigneUpdate->value, $modal['callback_id']);
        $this->assertEquals('Modifier enseigne', $modal['title']['text']);

        $nameBlock = collect($modal['blocks'])->firstWhere('block_id', 'name');
        $this->assertEquals('Existing Vendor', $nameBlock['element']['initial_value']);

        $metadata = json_decode($modal['private_metadata'], true);
        $this->assertEquals($vendor->id, $metadata['vendor_id']);
    }

    public function test_edit_vendor_modal_includes_active_toggle(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['active' => true]);

        $modal = $this->builder->editVendorModal($vendor);

        $activeBlock = collect($modal['blocks'])->firstWhere('block_id', 'active');
        $this->assertNotNull($activeBlock);
        $this->assertEquals('static_select', $activeBlock['element']['type']);
        $this->assertEquals('1', $activeBlock['element']['initial_option']['value']);
    }

    // --- orderModal ---

    public function test_order_modal_create_mode_has_empty_fields(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();

        $modal = $this->builder->orderModal($proposal, null, false, false);

        $this->assertEquals(SlackAction::CallbackOrderCreate->value, $modal['callback_id']);
        $this->assertEquals('Nouvelle commande', $modal['title']['text']);
        $descBlock = collect($modal['blocks'])->firstWhere('block_id', 'description');
        $this->assertEquals('', $descBlock['element']['initial_value']);
    }

    public function test_order_modal_edit_mode_pre_fills_order_data(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'description' => 'Pad Thai',
            'price_estimated' => 14.50,
        ]);

        $modal = $this->builder->orderModal($proposal, $order, false, true);

        $this->assertEquals(SlackAction::CallbackOrderEdit->value, $modal['callback_id']);
        $this->assertEquals('Modifier commande', $modal['title']['text']);

        $descBlock = collect($modal['blocks'])->firstWhere('block_id', 'description');
        $this->assertEquals('Pad Thai', $descBlock['element']['initial_value']);

        $metadata = json_decode($modal['private_metadata'], true);
        $this->assertEquals($order->id, $metadata['order_id']);
    }

    public function test_order_modal_shows_final_price_when_allowed(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();

        $modal = $this->builder->orderModal($proposal, null, true, false);

        $priceFinalBlock = collect($modal['blocks'])->firstWhere('block_id', 'price_final');
        $this->assertNotNull($priceFinalBlock);
    }

    public function test_order_modal_hides_final_price_when_not_allowed(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();

        $modal = $this->builder->orderModal($proposal, null, false, false);

        $priceFinalBlock = collect($modal['blocks'])->firstWhere('block_id', 'price_final');
        $this->assertNull($priceFinalBlock);
    }

    // --- delegateModal ---

    public function test_delegate_modal_includes_user_select_and_metadata(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();

        $modal = $this->builder->delegateModal($proposal, 'runner');

        $this->assertEquals(SlackAction::CallbackRoleDelegate->value, $modal['callback_id']);
        $this->assertEquals('Deleguer le role', $modal['title']['text']);

        $delegateBlock = collect($modal['blocks'])->firstWhere('block_id', 'delegate');
        $this->assertEquals('users_select', $delegateBlock['element']['type']);

        $metadata = json_decode($modal['private_metadata'], true);
        $this->assertEquals($proposal->id, $metadata['proposal_id']);
        $this->assertEquals('runner', $metadata['role']);
    }

    // --- adjustPriceModal ---

    public function test_adjust_price_modal_lists_orders_with_current_prices(): void
    {
        $proposal = VendorProposal::factory()->for($this->organization)->create();
        $order = Order::factory()->for($proposal)->create([
            'organization_id' => $this->organization->id,
            'provider_user_id' => 'U_USER',
            'description' => 'Miso Soup',
        ]);

        $modal = $this->builder->adjustPriceModal($proposal, [$order]);

        $this->assertEquals(SlackAction::CallbackOrderAdjustPrice->value, $modal['callback_id']);

        $orderBlock = collect($modal['blocks'])->firstWhere('block_id', 'order');
        $this->assertNotNull($orderBlock);
        $options = $orderBlock['element']['options'];
        $this->assertCount(1, $options);
        $this->assertEquals((string) $order->id, $options[0]['value']);
    }

    // --- proposeRestaurantModal ---

    public function test_propose_restaurant_modal_includes_all_fields(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $modal = $this->builder->proposeRestaurantModal($session);

        $this->assertEquals(SlackAction::CallbackRestaurantPropose->value, $modal['callback_id']);
        $this->assertEquals('Proposer un restaurant', $modal['title']['text']);

        $blockIds = collect($modal['blocks'])->pluck('block_id')->filter()->values()->toArray();
        $this->assertContains('name', $blockIds);
        $this->assertContains('url_website', $blockIds);
        $this->assertContains('fulfillment_types', $blockIds);
        $this->assertContains('deadline', $blockIds);
        $this->assertContains('note', $blockIds);
        $this->assertContains('help', $blockIds);
        $this->assertContains('file', $blockIds);
    }

    public function test_propose_restaurant_modal_includes_fulfillment_checkboxes(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $modal = $this->builder->proposeRestaurantModal($session);

        $fulfillmentBlock = collect($modal['blocks'])->firstWhere('block_id', 'fulfillment_types');
        $this->assertEquals('checkboxes', $fulfillmentBlock['element']['type']);
        $options = $fulfillmentBlock['element']['options'];
        $values = array_column($options, 'value');
        $this->assertContains(FulfillmentType::Pickup->value, $values);
        $this->assertContains(FulfillmentType::Delivery->value, $values);
        $this->assertContains(FulfillmentType::OnSite->value, $values);
    }

    public function test_propose_restaurant_modal_includes_file_upload(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $modal = $this->builder->proposeRestaurantModal($session);

        $fileBlock = collect($modal['blocks'])->firstWhere('block_id', 'file');
        $this->assertNotNull($fileBlock);
        $this->assertEquals('file_input', $fileBlock['element']['type']);
        $this->assertEquals(1, $fileBlock['element']['max_files']);
    }

    // --- vendorExportModal ---

    public function test_vendor_export_modal_displays_json_data(): void
    {
        $json = json_encode(['vendors' => [['name' => 'Test']]], JSON_PRETTY_PRINT);

        $modal = $this->builder->vendorExportModal($json);

        $this->assertEquals('modal', $modal['type']);
        $this->assertEquals('Export Vendors', $modal['title']['text']);

        $jsonChunk = collect($modal['blocks'])->firstWhere('block_id', 'json_chunk_0');
        $this->assertNotNull($jsonChunk);
        $this->assertStringContainsString('Test', $jsonChunk['text']['text']);
    }

    public function test_proposal_blocks_shows_unassigned_runner(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->pickup()
            ->create(['runner_user_id' => null]);

        $blocks = $this->builder->proposalBlocks($proposal, 0);

        $sectionBlock = collect($blocks)->firstWhere('type', 'section');
        $this->assertStringContainsString('non assigne', $sectionBlock['text']['text']);
    }

    public function test_recap_modal_shows_empty_orders_message(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create();
        $proposal = VendorProposal::factory()
            ->for($this->organization)
            ->for($vendor)
            ->create();

        $modal = $this->builder->recapModal($proposal, [], ['estimated' => '0.00', 'final' => '0.00']);

        $emptySection = collect($modal['blocks'])->first(fn ($b) => ($b['type'] ?? '') === 'section' && str_contains($b['text']['text'] ?? '', 'Aucune commande'));
        $this->assertNotNull($emptySection);
    }

    public function test_edit_vendor_modal_shows_inactive_status(): void
    {
        $vendor = Vendor::factory()->for($this->organization)->create(['active' => false]);

        $modal = $this->builder->editVendorModal($vendor);

        $activeBlock = collect($modal['blocks'])->firstWhere('block_id', 'active');
        $this->assertEquals('0', $activeBlock['element']['initial_option']['value']);
    }
}
