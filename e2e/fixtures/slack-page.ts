import { test as base, expect, Page, BrowserContext } from '@playwright/test';
import path from 'path';
import { SlackSelectors } from '../helpers/slack-selectors';

const AUTH_DIR = path.resolve(__dirname, '../auth');

/**
 * SlackPage encapsulates all Slack UI interactions.
 * It navigates channels, clicks bot buttons, fills modals, and reads messages.
 */
export class SlackPage {
  constructor(
    public readonly page: Page,
    public readonly channelName: string
  ) {}

  // ── Navigation ─────────────────────────────────────────────

  async goToChannel(): Promise<void> {
    const channelLink = this.page.locator(
      SlackSelectors.channelLink(this.channelName)
    );

    if (await channelLink.isVisible({ timeout: 3000 }).catch(() => false)) {
      await channelLink.click();
    } else {
      // Use Slack's quick switcher (Cmd+K / Ctrl+K)
      await this.page.keyboard.press('Meta+k');
      await this.page
        .locator(SlackSelectors.quickSwitcherInput)
        .fill(this.channelName);
      await this.page
        .locator(SlackSelectors.quickSwitcherResult(this.channelName))
        .first()
        .click();
    }

    await this.page.waitForSelector(SlackSelectors.messageList, {
      timeout: 10_000,
    });
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
    let scope = this.page;
    if (parentText) {
      const parent = this.page
        .locator(SlackSelectors.messageContaining(parentText))
        .first();
      await parent.waitFor({ timeout: 10_000 });
      scope = parent as any;
    }
    const btn = (scope as any).locator(SlackSelectors.button(buttonText)).first();
    await btn.waitFor({ timeout: 10_000 });
    await btn.click();
  }

  async clickButtonByActionId(actionId: string): Promise<void> {
    const btn = this.page
      .locator(`button[data-qa-action-id="${actionId}"], [data-action-id="${actionId}"] button`)
      .first();
    await btn.waitFor({ timeout: 10_000 });
    await btn.click();
  }

  // ── Slash commands ─────────────────────────────────────────

  async sendSlashCommand(command: string): Promise<void> {
    const composer = this.page.locator(SlackSelectors.messageComposer);
    await composer.click();
    await composer.fill(command);
    await this.page.keyboard.press('Enter');
  }

  // ── Modals ─────────────────────────────────────────────────

  async waitForModal(titleText?: string): Promise<void> {
    await this.page
      .locator(SlackSelectors.modal)
      .first()
      .waitFor({ timeout: 10_000 });

    if (titleText) {
      await expect(
        this.page.locator(SlackSelectors.modalTitle)
      ).toContainText(titleText, { timeout: 5_000 });
    }
  }

  async fillModalField(blockId: string, value: string): Promise<void> {
    const field = this.page
      .locator(SlackSelectors.modalInput(blockId))
      .first();
    await field.waitFor({ timeout: 5_000 });
    await field.fill(value);
  }

  async selectModalOption(blockId: string, optionText: string): Promise<void> {
    // Click the select trigger
    const select = this.page
      .locator(SlackSelectors.modalSelect(blockId))
      .first();
    await select.click();

    // Select option from dropdown
    await this.page
      .locator(SlackSelectors.selectOption(optionText))
      .first()
      .click();
  }

  async checkModalCheckbox(blockId: string, value: string): Promise<void> {
    const checkbox = this.page
      .locator(`[data-qa-block-id="${blockId}"] input[type="checkbox"][value="${value}"]`)
      .first();
    if (!(await checkbox.isChecked())) {
      await checkbox.check();
    }
  }

  async submitModal(): Promise<void> {
    const submitBtn = this.page
      .locator(SlackSelectors.modalSubmit)
      .first();
    await submitBtn.click();
  }

  async getModalErrorText(): Promise<string | null> {
    const error = this.page.locator(SlackSelectors.modalError);
    if (await error.isVisible({ timeout: 3_000 }).catch(() => false)) {
      return error.innerText();
    }
    return null;
  }

  async isModalVisible(): Promise<boolean> {
    return this.page
      .locator(SlackSelectors.modal)
      .isVisible({ timeout: 3_000 })
      .catch(() => false);
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
      .first();
    await msg.hover();
    const threadBtn = msg.locator(SlackSelectors.threadButton);
    await threadBtn.click();
    await this.page.waitForSelector(SlackSelectors.threadPanel, {
      timeout: 10_000,
    });
  }

  async closeThread(): Promise<void> {
    const closeBtn = this.page.locator(SlackSelectors.threadCloseButton);
    if (await closeBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await closeBtn.click();
    }
  }

  // ── User select (for delegation) ──────────────────────────

  async selectUser(blockId: string, userName: string): Promise<void> {
    const userSelect = this.page
      .locator(`[data-qa-block-id="${blockId}"] [data-qa="user-select"]`)
      .first();
    await userSelect.click();
    await this.page.locator(`[data-qa="user-select-input"]`).fill(userName);
    await this.page
      .locator(`[data-qa="user-select-option"]`)
      .filter({ hasText: userName })
      .first()
      .click();
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

// ── Playwright fixture with multi-user support ────────────────

type SlackFixtures = {
  slackPageA: SlackPage;
  slackPageB: SlackPage;
  slackPageAdmin: SlackPage;
};

export const test = base.extend<SlackFixtures>({
  slackPageA: async ({ browser }, use) => {
    const context = await browser.newContext({
      storageState: path.join(AUTH_DIR, 'user-a.json'),
    });
    const page = await context.newPage();
    const slackPage = new SlackPage(
      page,
      process.env.SLACK_TEST_CHANNEL_NAME!
    );
    await slackPage.goToChannel();
    await use(slackPage);
    await context.close();
  },

  slackPageB: async ({ browser }, use) => {
    const context = await browser.newContext({
      storageState: path.join(AUTH_DIR, 'user-b.json'),
    });
    const page = await context.newPage();
    const slackPage = new SlackPage(
      page,
      process.env.SLACK_TEST_CHANNEL_NAME!
    );
    await slackPage.goToChannel();
    await use(slackPage);
    await context.close();
  },

  slackPageAdmin: async ({ browser }, use) => {
    const context = await browser.newContext({
      storageState: path.join(AUTH_DIR, 'admin.json'),
    });
    const page = await context.newPage();
    const slackPage = new SlackPage(
      page,
      process.env.SLACK_TEST_CHANNEL_NAME!
    );
    await slackPage.goToChannel();
    await use(slackPage);
    await context.close();
  },
});

export { expect } from '@playwright/test';
