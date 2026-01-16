<?php

namespace App\Slack;

class SlackActions
{
    public const OPEN_PROPOSAL_MODAL = 'open_proposal_modal';

    public const OPEN_ADD_ENSEIGNE_MODAL = 'open_add_enseigne_modal';

    public const CLOSE_DAY = 'close_day';

    public const CLAIM_RUNNER = 'claim_runner';

    public const CLAIM_ORDERER = 'claim_orderer';

    public const OPEN_ORDER_MODAL = 'open_order_modal';

    public const OPEN_EDIT_ORDER_MODAL = 'open_edit_order_modal';

    public const OPEN_SUMMARY = 'open_summary';

    public const OPEN_DELEGATE_MODAL = 'open_delegate_modal';

    public const OPEN_ADJUST_PRICE_MODAL = 'open_adjust_price_modal';

    public const OPEN_MANAGE_ENSEIGNE_MODAL = 'open_manage_enseigne_modal';

    public const CALLBACK_PROPOSAL_CREATE = 'proposal.create';

    public const CALLBACK_ENSEIGNE_CREATE = 'enseigne.create';

    public const CALLBACK_ENSEIGNE_UPDATE = 'enseigne.update';

    public const CALLBACK_ORDER_CREATE = 'order.create';

    public const CALLBACK_ORDER_EDIT = 'order.edit';

    public const CALLBACK_ROLE_DELEGATE = 'role.delegate';

    public const CALLBACK_ORDER_ADJUST_PRICE = 'order.adjust_price';
}
