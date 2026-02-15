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

    // Dev actions
    case DevResetDatabase = 'dev.reset_database';
    case DevExportVendors = 'dev.export_vendors';

    // Vendor list actions
    case DashboardVendorsList = 'dashboard.vendors_list';
    case VendorsListSearch = 'vendors_list.search';
    case VendorsListEdit = 'vendors_list.edit';

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

    public function isSession(): bool
    {
        return in_array($this, [
            self::OpenLunchDashboard,
            self::SessionClose,
            self::CloseDay,
            self::DashboardCloseSession,
        ], true);
    }

    public function isOrder(): bool
    {
        return in_array($this, [
            self::OpenOrderForProposal,
            self::OrderOpenEdit,
            self::OrderDelete,
            self::DashboardJoinProposal,
            self::DashboardOrderHere,
            self::DashboardMyOrder,
            self::OpenOrderModal,
            self::OpenEditOrderModal,
        ], true);
    }

    public function isVendor(): bool
    {
        return in_array($this, [
            self::OpenAddEnseigneModal,
            self::OpenManageEnseigneModal,
            self::DashboardVendorsList,
            self::VendorsListSearch,
            self::VendorsListEdit,
        ], true);
    }

    public function isDev(): bool
    {
        return in_array($this, [
            self::DevResetDatabase,
            self::DevExportVendors,
        ], true);
    }

    public function isProposal(): bool
    {
        return in_array($this, [
            self::OpenProposalModal,
            self::DashboardStartFromCatalog,
            self::DashboardRelaunch,
            self::DashboardCreateProposal,
            self::DashboardChooseFavorite,
            self::DashboardProposeVendor,
            self::ProposalOpenManage,
            self::ProposalTakeCharge,
            self::ProposalOpenRecap,
            self::ProposalClose,
            self::ProposalSetStatus,
            self::ClaimRunner,
            self::ClaimOrderer,
            self::OpenDelegateModal,
            self::OpenAdjustPriceModal,
            self::OpenSummary,
            self::DashboardClaimResponsible,
            self::DashboardViewOrders,
        ], true);
    }
}
