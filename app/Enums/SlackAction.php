<?php

namespace App\Enums;

enum SlackAction: string
{
    // Legacy block actions (channel messages)
    case OpenProposalModal = 'open_proposal_modal';
    case OpenAddEnseigneModal = 'open_add_enseigne_modal';
    case CloseDay = 'close_day';
    case ClaimRunner = 'claim_runner';
    case ClaimOrderer = 'claim_orderer';
    case OpenOrderModal = 'open_order_modal';
    case OpenEditOrderModal = 'open_edit_order_modal';
    case OpenSummary = 'open_summary';
    case OpenDelegateModal = 'open_delegate_modal';
    case OpenAdjustPriceModal = 'open_adjust_price_modal';
    case OpenManageEnseigneModal = 'open_manage_enseigne_modal';

    // View callbacks
    case CallbackProposalCreate = 'proposal_create';
    case CallbackRestaurantPropose = 'restaurant_propose';
    case CallbackEnseigneCreate = 'enseigne_create';
    case CallbackEnseigneUpdate = 'enseigne_update';
    case CallbackOrderCreate = 'order_create';
    case CallbackOrderEdit = 'order_edit';
    case CallbackRoleDelegate = 'role_delegate';
    case CallbackOrderAdjustPrice = 'order_adjust_price';
    case CallbackLunchDashboard = 'lunch_dashboard';
    case CallbackProposalManage = 'proposal_manage';
    case CallbackProposalRecap = 'proposal_recap';
    case CallbackOrderDelete = 'order_delete';

    // Dashboard actions (manifeste pattern: namespace.action)
    case DashboardCreateProposal = 'dashboard.create_proposal';
    case DashboardStartFromCatalog = 'dashboard.start_from_catalog';
    case DashboardJoinProposal = 'dashboard.join_proposal';
    case DashboardRelaunch = 'dashboard.relaunch';

    // Order actions
    case OrderOpenEdit = 'order.open_edit';
    case OrderDelete = 'order.delete';

    // Proposal actions (responsable)
    case ProposalOpenManage = 'proposal.open_manage';
    case ProposalOpenRecap = 'proposal.open_recap';
    case ProposalClose = 'proposal.close';
    case ProposalSetStatus = 'proposal.set_status';
    case ProposalTakeCharge = 'proposal.take_charge';

    // Session actions
    case SessionClose = 'session.close';

    // Channel message navigation buttons (post-order creation)
    case OpenOrderForProposal = 'open_order_for_proposal';
    case OpenLunchDashboard = 'open_lunch_dashboard';

    // Legacy dashboard actions (kept for backward compatibility)
    case DashboardProposeVendor = 'dashboard_propose_vendor';
    case DashboardChooseFavorite = 'dashboard_choose_favorite';
    case DashboardOrderHere = 'dashboard_order_here';
    case DashboardClaimResponsible = 'dashboard_claim_responsible';
    case DashboardViewOrders = 'dashboard_view_orders';
    case DashboardMyOrder = 'dashboard_my_order';
    case DashboardCloseSession = 'dashboard_close_session';

    public function isCallback(): bool
    {
        return str_contains($this->value, '_') && str_starts_with($this->value, 'proposal_')
            || str_starts_with($this->value, 'order_')
            || str_starts_with($this->value, 'lunch_')
            || str_starts_with($this->value, 'enseigne_')
            || str_starts_with($this->value, 'restaurant_')
            || str_starts_with($this->value, 'role_');
    }

    public function isDashboard(): bool
    {
        return str_starts_with($this->value, 'dashboard');
    }
}
