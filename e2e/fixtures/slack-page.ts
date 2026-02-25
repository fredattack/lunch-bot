import { test as base, expect, Page, BrowserContext } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';
import { SlackSelectors, BlockIdToLabel, ValueToLabel } from '../helpers/slack-selectors';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const AUTH_DIR = path.resolve(__dirname, '../auth');

/**
 * SlackPage encapsulates all Slack UI interactions.
 * It navigates channels, clicks bot buttons, fills modals, and reads messages.
 */
export class SlackPage {
  constructor(
    public readonly page: Page,
    public readonly channelName: string,
    public readonly userId?: string
  ) {}

  // ── Navigation ─────────────────────────────────────────────

  async goToChannel(): Promise<void> {
    // Click the channel in the sidebar tree
    const channelItem = this.page.getByRole('treeitem', { name: this.channelName, exact: true });

    if (await channelItem.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await channelItem.click();
    } else {
      // Fallback: use Slack's quick switcher (Cmd+K / Ctrl+K)
      await this.page.keyboard.press('Meta+k');
      const searchInput = this.page.getByRole('searchbox').or(
        this.page.locator(SlackSelectors.quickSwitcherInput)
      );
      await searchInput.first().fill(this.channelName);
      await this.page
        .getByRole('option', { name: this.channelName })
        .or(this.page.locator(SlackSelectors.quickSwitcherResult(this.channelName)))
        .first()
        .click();
    }

    // Wait for the message composer to confirm the channel is loaded
    await this.page.getByRole('textbox', { name: new RegExp(this.channelName, 'i') })
      .or(this.page.locator(SlackSelectors.messageComposer))
      .first()
      .waitFor({ state: 'visible', timeout: 10_000 });
  }

  // ── Messages ───────────────────────────────────────────────

  async getLastBotMessage(): Promise<string> {
    const messages = this.page.locator(SlackSelectors.botMessage);
    const last = messages.last();
    await last.waitFor({ timeout: 10_000 });
    return last.innerText();
  }

  async waitForMessageContaining(text: string, timeout = 15_000): Promise<void> {
    await this.page
      .locator(SlackSelectors.messageContaining(text))
      .first()
      .waitFor({ timeout });
  }

  async getMessageContaining(text: string): Promise<string> {
    const msg = this.page.locator(SlackSelectors.messageContaining(text)).first();
    await msg.waitFor({ timeout: 10_000 });
    return msg.innerText();
  }

  // ── Buttons (Block Kit actions) ────────────────────────────

  async clickButton(buttonText: string, parentText?: string): Promise<void> {
    if (parentText) {
      // Scope to the most recent matching message (oldest may be stale after DB reset)
      const parent = this.page
        .locator(SlackSelectors.messageContaining(parentText))
        .last();
      await parent.waitFor({ timeout: 10_000 });
      const btn = (parent as any).locator(SlackSelectors.button(buttonText)).first();
      await btn.waitFor({ timeout: 10_000 });
      await btn.click({ force: true });
    } else if (await this.isModalVisible()) {
      // If a modal is open, look for the button inside it first
      const dialog = this.getDialog();
      const btn = dialog.locator(SlackSelectors.button(buttonText)).first();
      if (await btn.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await btn.click({ force: true });
        return;
      }
      // Fallback to page-wide search (last to get most recent)
      const pageBtn = this.page.locator(SlackSelectors.button(buttonText)).last();
      await pageBtn.waitFor({ timeout: 10_000 });
      await pageBtn.click({ force: true });
    } else {
      // No modal open — use .last() to get most recent channel button
      const btn = this.page.locator(SlackSelectors.button(buttonText)).last();
      await btn.waitFor({ timeout: 10_000 });
      await btn.click({ force: true });
    }
  }

  async clickButtonByActionId(actionId: string): Promise<void> {
    const btn = this.page
      .locator(`button[data-qa-action-id="${actionId}"], [data-action-id="${actionId}"] button`)
      .first();
    await btn.waitFor({ timeout: 10_000 });
    await btn.click({ force: true });
  }

  async isButtonVisible(buttonText: string, parentText?: string, timeout = 5_000): Promise<boolean> {
    let scope = this.page;
    if (parentText) {
      const parent = this.page
        .locator(SlackSelectors.messageContaining(parentText))
        .last();
      if (!(await parent.isVisible({ timeout: 3_000 }).catch(() => false))) {
        return false;
      }
      scope = parent as any;
    }
    return (scope as any)
      .locator(SlackSelectors.button(buttonText))
      .first()
      .isVisible({ timeout })
      .catch(() => false);
  }

  // ── Slash commands ─────────────────────────────────────────

  async sendSlashCommand(command: string): Promise<void> {
    // Dismiss any open modal — Escape when none is open is harmless
    await this.page.keyboard.press('Escape');
    await this.page.waitForTimeout(300);

    const composer = this.page.getByRole('textbox', { name: new RegExp(`${this.channelName}`, 'i') });
    await composer.click({ timeout: 5_000 });
    await composer.pressSequentially(command, { delay: 50 });
    // Wait for Slack's slash command autocomplete to appear
    await this.page.waitForTimeout(1_000);
    // Enter once to select from autocomplete, then again to send
    await this.page.keyboard.press('Enter');
    await this.page.waitForTimeout(300);
    await this.page.keyboard.press('Enter');
  }

  // ── Modals ─────────────────────────────────────────────────

  /** Get the active Block Kit modal dialog (wizard_modal, not the sandbox banner) */
  private getDialog() {
    return this.page.locator('[data-qa="wizard_modal"]').last();
  }

  async waitForModal(titleText?: string): Promise<void> {
    if (titleText) {
      await this.page.getByRole('dialog', { name: new RegExp(titleText, 'i') })
        .waitFor({ timeout: 10_000 });
    } else {
      await this.getDialog().waitFor({ timeout: 10_000 });
    }
  }

  async waitForModalContent(timeout = 10_000): Promise<void> {
    const dialog = this.getDialog();
    await dialog.locator('input, textarea, label, [role="combobox"]')
      .first()
      .waitFor({ state: 'visible', timeout });
  }

  /**
   * Find an input/textarea field in the modal by label text(s).
   * Uses multiple strategies and retries to handle modal transitions.
   * When multiple labels are provided, all are tried on each iteration
   * to avoid wasting time on non-matching labels sequentially.
   */
  private async findFieldByLabel(labels: string | string[], timeout = 10_000): Promise<any | null> {
    const labelArray = Array.isArray(labels) ? labels : [labels];
    const deadline = Date.now() + timeout;
    const checkTimeout = 200;

    while (Date.now() < deadline) {
      const dialog = this.getDialog();

      for (const labelText of labelArray) {
        const regex = new RegExp(labelText, 'i');

        // Strategy A: Playwright's getByLabel (handles for/id, aria-labelledby, wrapping labels)
        const byLabel = dialog.getByLabel(regex).first();
        if (await byLabel.isVisible({ timeout: checkTimeout }).catch(() => false)) {
          return byLabel;
        }

        // Strategy B: Find label element → get its id → find input via aria-labelledby
        const label = dialog.locator('label').filter({ hasText: regex }).first();
        if (await label.isVisible({ timeout: checkTimeout }).catch(() => false)) {
          const labelId = await label.getAttribute('id');
          if (labelId) {
            const field = dialog.locator(`[aria-labelledby="${labelId}"]`).first();
            if (await field.isVisible({ timeout: checkTimeout }).catch(() => false)) {
              return field;
            }
          }

          // Strategy C: find input/textarea within the same input block container
          const block = dialog.locator('.p-block_kit_input_block').filter({
            has: dialog.locator(`label:has-text("${labelText}")`)
          }).first();
          if (await block.isVisible({ timeout: checkTimeout }).catch(() => false)) {
            const input = block.locator('input:not([type="hidden"]):not([type="checkbox"]), textarea').first();
            if (await input.isVisible({ timeout: checkTimeout }).catch(() => false)) {
              return input;
            }
          }
        }
      }

      // Modal may still be transitioning, retry
      await this.page.waitForTimeout(300);
    }

    return null;
  }

  async fillModalField(blockId: string, value: string): Promise<void> {
    const labelEntry = BlockIdToLabel[blockId];
    const labels = labelEntry ? (Array.isArray(labelEntry) ? labelEntry : [labelEntry]) : [];

    // Strategy 1: find by label text (all labels tried on each retry iteration)
    if (labels.length > 0) {
      const field = await this.findFieldByLabel(labels);
      if (field) {
        await field.fill(value);
        return;
      }
    }

    // Strategy 2: data-qa-block-id selectors (scoped to dialog)
    const dialog = this.getDialog();
    const byBlockId = dialog.locator(
      `[data-qa-block-id="${blockId}"] input:not([type="hidden"]), [data-qa-block-id="${blockId}"] textarea, ` +
      `[data-block-id="${blockId}"] input:not([type="hidden"]), [data-block-id="${blockId}"] textarea`
    ).first();
    if (await byBlockId.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await byBlockId.fill(value);
      return;
    }

    // Strategy 3: find by ARIA role textbox with label name
    for (const label of labels) {
      const ariaField = dialog.getByRole('textbox', { name: new RegExp(label, 'i') });
      if (await ariaField.isVisible({ timeout: 1_000 }).catch(() => false)) {
        await ariaField.fill(value);
        return;
      }
    }

    // Strategy 4: page-wide search for block_id (fallback for different modal structures)
    const pageInput = this.page.locator(
      `[data-qa-block-id="${blockId}"] input:not([type="hidden"]), [data-qa-block-id="${blockId}"] textarea, ` +
      `[data-block-id="${blockId}"] input:not([type="hidden"]), [data-block-id="${blockId}"] textarea`
    ).first();
    if (await pageInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await pageInput.fill(value);
      return;
    }

    // Diagnostic output before throwing
    const isVisible = await dialog.isVisible().catch(() => false);
    const content = isVisible ? await dialog.innerHTML().catch(() => 'N/A') : 'modal not visible';
    console.error(`fillModalField debug: blockId="${blockId}", modal visible=${isVisible}, content length=${content.length}`);
    console.error(`fillModalField debug: first 500 chars of modal content: ${content.substring(0, 500)}`);
    throw new Error(`fillModalField: Could not find field for blockId="${blockId}"`);
  }

  async selectModalOption(blockId: string, optionText: string): Promise<void> {
    const dialog = this.getDialog();
    let combobox: any = null;

    // Strategy 1: find combobox by label text (all labels tried per iteration)
    const labelEntry = BlockIdToLabel[blockId];
    if (labelEntry) {
      const labels = Array.isArray(labelEntry) ? labelEntry : [labelEntry];
      const field = await this.findFieldByLabel(labels);
      if (field) {
        const role = await field.getAttribute('role');
        if (role === 'combobox') {
          combobox = field;
        }
      }
    }

    // Strategy 2: data-qa-block-id / data-block-id selectors (legacy)
    if (!combobox) {
      const select = this.page
        .locator(SlackSelectors.modalSelect(blockId))
        .first();
      if (await select.isVisible({ timeout: 2_000 }).catch(() => false)) {
        combobox = select;
      }
    }

    if (!combobox) {
      throw new Error(`selectModalOption: Could not find combobox for blockId="${blockId}"`);
    }

    // force: true bypasses Slack's c-select_input__content overlay intercepting pointer events
    await combobox.click({ force: true });
    await this.page.waitForTimeout(300);

    const option = this.page.getByRole('option', { name: optionText })
      .or(this.page.locator(SlackSelectors.selectOption(optionText)));

    // For static selects, options are immediately visible after click — select directly
    if (await option.first().isVisible({ timeout: 2_000 }).catch(() => false)) {
      await option.first().click({ force: true });
      return;
    }

    // Fallback: type to search (for external data source selects)
    await combobox.fill(optionText);
    await this.page.waitForTimeout(500);
    await option.first().click({ timeout: 5_000, force: true });
  }

  async checkModalCheckbox(blockId: string, value: string): Promise<void> {
    // Try data-qa-block-id first
    let checkbox = this.page
      .locator(`[data-qa-block-id="${blockId}"] input[type="checkbox"][value="${value}"]`)
      .first();
    if (!(await checkbox.isVisible({ timeout: 2_000 }).catch(() => false))) {
      // Fallback: find checkbox by its display label in the dialog
      const dialog = this.getDialog();
      const displayLabel = ValueToLabel[value] || value;
      checkbox = dialog.getByRole('checkbox', { name: new RegExp(displayLabel, 'i') });
    }
    if (!(await checkbox.isChecked())) {
      await checkbox.check();
    }
  }

  async uncheckModalCheckbox(blockId: string, value: string): Promise<void> {
    let checkbox = this.page
      .locator(`[data-qa-block-id="${blockId}"] input[type="checkbox"][value="${value}"]`)
      .first();
    if (!(await checkbox.isVisible({ timeout: 2_000 }).catch(() => false))) {
      const dialog = this.getDialog();
      const displayLabel = ValueToLabel[value] || value;
      checkbox = dialog.getByRole('checkbox', { name: new RegExp(displayLabel, 'i') });
    }
    if (await checkbox.isChecked()) {
      await checkbox.uncheck();
    }
  }

  async submitModal(): Promise<void> {
    const submitBtn = this.page
      .locator(SlackSelectors.modalSubmit)
      .first();
    if (await submitBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await submitBtn.click({ force: true });
      return;
    }
    // Fallback: find the last button in the dialog footer (submit is always last)
    const dialog = this.getDialog();
    const buttons = dialog.getByRole('button');
    const count = await buttons.count();
    // The submit button is the last one in the dialog
    await buttons.nth(count - 1).click({ force: true });
  }

  async getModalErrorText(): Promise<string | null> {
    // Check for Block Kit error elements
    const error = this.page.locator(SlackSelectors.modalError);
    if (await error.isVisible({ timeout: 3_000 }).catch(() => false)) {
      return error.innerText();
    }
    // Check for alert role anywhere on the page (Slack native validation)
    const alertEl = this.page.getByRole('alert');
    const alertCount = await alertEl.count();
    for (let i = 0; i < alertCount; i++) {
      const a = alertEl.nth(i);
      const text = await a.innerText().catch(() => '');
      if (text && !text.includes('Suggestions de commandes')) {
        return text;
      }
    }
    // Check for inline error text inside the dialog
    const dialog = this.getDialog();
    const inlineError = dialog.locator('[data-qa="block-kit-error"], .p-block_kit_modal__error');
    if (await inlineError.isVisible({ timeout: 1_000 }).catch(() => false)) {
      return inlineError.innerText();
    }
    return null;
  }

  async isModalVisible(): Promise<boolean> {
    return this.getDialog()
      .isVisible({ timeout: 3_000 })
      .catch(() => false);
  }

  async getModalContent(): Promise<string> {
    const dialog = this.getDialog();
    await dialog.waitFor({ timeout: 10_000 });
    return dialog.innerText();
  }

  async dismissModal(): Promise<void> {
    await this.page.keyboard.press('Escape');
    await this.wait(500);

    // Handle Slack's "Voulez-vous vraiment quitter l'espace de travail?" confirmation
    const dialog = this.getDialog();
    const quitBtn = dialog.locator('button:has-text("Quitter")').first();
    if (await quitBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await quitBtn.click();
    }

    // Wait for modal to fully close
    await dialog.waitFor({ state: 'hidden', timeout: 5_000 }).catch(() => {});
  }

  // ── Ephemeral messages ─────────────────────────────────────

  async waitForEphemeral(text: string, timeout = 10_000): Promise<void> {
    await this.page
      .locator(SlackSelectors.ephemeralContaining(text))
      .first()
      .waitFor({ timeout });
  }

  async getEphemeralText(): Promise<string | null> {
    const eph = this.page.locator(SlackSelectors.ephemeral).last();
    if (await eph.isVisible({ timeout: 5_000 }).catch(() => false)) {
      return eph.innerText();
    }
    return null;
  }

  // ── Thread ─────────────────────────────────────────────────

  async openThread(messageText: string): Promise<void> {
    const msg = this.page
      .locator(SlackSelectors.messageContaining(messageText))
      .last();
    await msg.waitFor({ timeout: 10_000 });

    // Strategy 1: click the "X réponse(s)" reply bar button (visible when thread has replies)
    const replyBar = msg.locator('button').filter({ hasText: /réponse|replies|reply/i }).first();
    if (await replyBar.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await replyBar.click();
    } else {
      // Strategy 2: hover to reveal message actions toolbar, then click "Répondre dans le fil"
      await msg.hover();
      await this.wait(500);

      const replyInThread = this.page.locator(
        'button:has-text("Répondre dans le fil"), button:has-text("Reply in thread"), ' +
        SlackSelectors.threadButton
      ).first();
      await replyInThread.waitFor({ timeout: 5_000 });
      await replyInThread.click();
    }

    // Wait for thread panel to appear
    const threadPanel = this.page.locator(
      SlackSelectors.threadPanel + ', [data-qa="thread_view_panel"]'
    );
    await threadPanel.first().waitFor({ state: 'visible', timeout: 10_000 });
    await this.wait(1000);
  }

  async closeThread(): Promise<void> {
    const closeBtn = this.page.locator(SlackSelectors.threadCloseButton);
    if (await closeBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await closeBtn.click();
    }
  }

  // ── User select (for delegation) ──────────────────────────

  async selectUser(blockId: string, userName: string): Promise<void> {
    // Try data-qa-block-id first
    let userSelect = this.page
      .locator(`[data-qa-block-id="${blockId}"] [data-qa="user-select"]`)
      .first();
    if (!(await userSelect.isVisible({ timeout: 2_000 }).catch(() => false))) {
      // Fallback: find by ARIA label in modal
      const labelEntry = BlockIdToLabel[blockId];
      if (labelEntry) {
        const labels = Array.isArray(labelEntry) ? labelEntry : [labelEntry];
        const dialog = this.getDialog();
        for (const label of labels) {
          const combobox = dialog.getByRole('combobox', { name: new RegExp(label, 'i') });
          if (await combobox.isVisible({ timeout: 2_000 }).catch(() => false)) {
            userSelect = combobox;
            break;
          }
        }
      }
    }
    await userSelect.click({ force: true });

    // Type to search for user
    const searchInput = this.page.locator(`[data-qa="user-select-input"]`);
    if (await searchInput.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await searchInput.fill(userName);
    } else {
      await this.page.keyboard.type(userName);
    }

    // Select the user from results
    const option = this.page
      .locator(`[data-qa="user-select-option"]`)
      .filter({ hasText: userName })
      .first();
    if (await option.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await option.click();
    } else {
      await this.page.getByRole('option', { name: new RegExp(userName, 'i') }).first().click();
    }
  }

  // ── Utilities ──────────────────────────────────────────────

  async reload(): Promise<void> {
    await this.page.reload();
    await this.page.waitForSelector(SlackSelectors.messageList, {
      timeout: 15_000,
    });
  }

  async wait(ms: number): Promise<void> {
    await this.page.waitForTimeout(ms);
  }
}

// ── Helper to create a SlackPage from a storage state ──────────

async function createSlackPage(
  browser: any,
  authFile: string,
  channelName: string,
  userId?: string
): Promise<{ slackPage: SlackPage; context: BrowserContext }> {
  const context = await browser.newContext({
    storageState: path.join(AUTH_DIR, authFile),
  });
  const page = await context.newPage();

  // Navigate to the Slack workspace first
  await page.goto(process.env.SLACK_WORKSPACE_URL!);
  await page.getByRole('tree').first().waitFor({ state: 'visible', timeout: 60_000 });

  const slackPage = new SlackPage(page, channelName, userId);
  await slackPage.goToChannel();
  return { slackPage, context };
}

// ── Playwright fixture with 4-user support ─────────────────────

type SlackFixtures = {
  slackPageA: SlackPage;
  slackPageB: SlackPage;
  slackPageC: SlackPage;
  slackPageAdmin: SlackPage;
};

export const test = base.extend<SlackFixtures>({
  slackPageA: async ({ browser }, use) => {
    const { slackPage, context } = await createSlackPage(
      browser,
      'user-a.json',
      process.env.SLACK_TEST_CHANNEL_NAME!,
      process.env.SLACK_TEST_USER_A_ID
    );
    await use(slackPage);
    await context.close();
  },

  slackPageB: async ({ browser }, use) => {
    const { slackPage, context } = await createSlackPage(
      browser,
      'user-b.json',
      process.env.SLACK_TEST_CHANNEL_NAME!,
      process.env.SLACK_TEST_USER_B_ID
    );
    await use(slackPage);
    await context.close();
  },

  slackPageC: async ({ browser }, use) => {
    const { slackPage, context } = await createSlackPage(
      browser,
      'user-c.json',
      process.env.SLACK_TEST_CHANNEL_NAME!,
      process.env.SLACK_TEST_USER_C_ID
    );
    await use(slackPage);
    await context.close();
  },

  slackPageAdmin: async ({ browser }, use) => {
    const { slackPage, context } = await createSlackPage(
      browser,
      'admin.json',
      process.env.SLACK_TEST_CHANNEL_NAME!,
      process.env.SLACK_TEST_ADMIN_ID
    );
    await use(slackPage);
    await context.close();
  },
});

export { expect } from '@playwright/test';
