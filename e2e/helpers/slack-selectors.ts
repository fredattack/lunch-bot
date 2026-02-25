/**
 * CSS / data-qa selectors for Slack Web UI.
 *
 * Slack uses data-qa attributes extensively.
 * These selectors are based on the Slack web client as of early 2026.
 * They may need adjustment if Slack updates their UI.
 */

/**
 * Mapping from backend block_id to Slack modal label text.
 * Used as fallback when data-qa-block-id attributes are not present.
 */
/**
 * Mapping from internal checkbox/option values to Slack modal display labels.
 */
export const ValueToLabel: Record<string, string> = {
  pickup: 'A emporter',
  delivery: 'Livraison',
  dine_in: 'Sur place',
};

export const BlockIdToLabel: Record<string, string | string[]> = {
  enseigne: 'Restaurant',
  fulfillment: 'Type',
  description: ['Description', 'Que voulez-vous', 'Votre commande'],
  price_estimated: ['Prix estime', 'Prix'],
  notes: 'Notes',
  destination: ['Ou allez-vous', 'Destination'],
  delay: ['Delai', 'en minutes'],
  note: ['Remarque', 'Note'],
  name: ['Nom', 'Nom de l'],
  fulfillment_types: ['Types disponibles', 'Type'],
  delegate: ['Choisir un membre', 'Deleguer'],
  order: ['Commande', 'Choisir'],
  price_final: 'Prix final',
  active: ['Statut', 'Active'],
  search: 'Recherche',
  deadline: ['Deadline', 'Heure limite'],
};

export const SlackSelectors = {
  // ── Navigation ─────────────────────────────────────────────
  channelLink: (name: string) =>
    `[data-qa="channel_sidebar_name_${name}"], a[data-qa-channel-sidebar-channel-id] span:has-text("${name}")`,
  quickSwitcherInput: '[data-qa="channel_switcher_input"], [data-qa="focusable_search_input"]',
  quickSwitcherResult: (name: string) =>
    `[data-qa="channel_switcher_result"]:has-text("${name}")`,

  // ── Messages ───────────────────────────────────────────────
  messageList: '[data-qa="slack_kit_list"], [data-qa="message_list"]',
  botMessage: '[data-qa="message_container"][data-qa-type="bot"]',
  messageContaining: (text: string) =>
    `[data-qa="message_container"]:has-text("${text}")`,

  // ── Buttons ────────────────────────────────────────────────
  button: (text: string) =>
    `button:has-text("${text}"), [role="button"]:has-text("${text}")`,

  // ── Slash command / composer ────────────────────────────────
  messageComposer:
    '[data-qa="message_input"] [contenteditable="true"], [data-qa="texty_composer_input"]',

  // ── Modal / Dialog ─────────────────────────────────────────
  modal: '[data-qa="wizard_modal"], [data-qa="modal"], [data-qa="slack_kit_modal"], .p-block_kit_modal, [role="dialog"][aria-modal="true"]',
  modalTitle: '[data-qa="wizard_modal_header"] h1, [data-qa="modal_title"], .p-block_kit_modal__title',
  modalSubmit:
    '[data-qa="wizard_modal_next"], [data-qa="modal_go_btn"], [data-qa="block_kit_modal_submit"], button[data-qa="modal_submit"]',
  modalError: '[data-qa="modal_error"], .p-block_kit_modal__error, [data-qa="block-kit-error"]',
  modalInput: (blockId: string) =>
    `[data-qa-block-id="${blockId}"] input, [data-qa-block-id="${blockId}"] textarea, [data-block-id="${blockId}"] input, [data-block-id="${blockId}"] textarea`,
  modalSelect: (blockId: string) =>
    `[data-qa-block-id="${blockId}"] [data-qa="select_input"], [data-block-id="${blockId}"] [data-qa="select_input"], [data-qa-block-id="${blockId}"] [role="combobox"], [data-block-id="${blockId}"] [role="combobox"], [data-qa-block-id="${blockId}"] button[aria-haspopup], [data-block-id="${blockId}"] button[aria-haspopup]`,
  selectOption: (text: string) =>
    `[data-qa="select_option"]:has-text("${text}"), [role="option"]:has-text("${text}"), [data-qa="select_menu_option"]:has-text("${text}")`,

  // ── Ephemeral messages ─────────────────────────────────────
  ephemeral: '[data-qa="ephemeral_message"], [data-qa="message_container"][data-qa-ephemeral="true"]',
  ephemeralContaining: (text: string) =>
    `[data-qa="ephemeral_message"]:has-text("${text}"), [data-qa="message_container"][data-qa-ephemeral="true"]:has-text("${text}")`,

  // ── Thread ─────────────────────────────────────────────────
  threadButton: '[data-qa="start_thread_button"], [data-qa="reply_bar"]',
  threadPanel: '[data-qa="thread_panel"], [data-qa="threads_flexpane"]',
  threadCloseButton: '[data-qa="close_flexpane"], [data-qa="thread_panel_close"]',

  // ── Confirmation dialog ────────────────────────────────────
  confirmButton: '[data-qa="dialog_go_btn"], button:has-text("Oui"), button:has-text("Confirmer")',
  cancelButton: '[data-qa="dialog_cancel_btn"], button:has-text("Annuler")',
} as const;
