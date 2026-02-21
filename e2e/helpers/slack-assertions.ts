import { expect } from '@playwright/test';
import { SlackPage } from '../fixtures/slack-page';

/**
 * Custom assertion helpers for verifying Slack bot behavior.
 */

export async function assertMessageVisible(
  slack: SlackPage,
  text: string,
  timeout = 15_000
): Promise<void> {
  await slack.waitForMessageContaining(text, timeout);
}

export async function assertEphemeralVisible(
  slack: SlackPage,
  text: string,
  timeout = 10_000
): Promise<void> {
  await slack.waitForEphemeral(text, timeout);
}

export async function assertModalOpen(
  slack: SlackPage,
  titleText?: string
): Promise<void> {
  await slack.waitForModal(titleText);
}

export async function assertModalClosed(slack: SlackPage): Promise<void> {
  const visible = await slack.isModalVisible();
  expect(visible).toBe(false);
}

export async function assertModalError(
  slack: SlackPage,
  errorText: string
): Promise<void> {
  const error = await slack.getModalErrorText();
  expect(error).not.toBeNull();
  expect(error).toContain(errorText);
}

export async function assertButtonVisible(
  slack: SlackPage,
  buttonText: string,
  parentText?: string
): Promise<void> {
  let scope: any = slack.page;
  if (parentText) {
    scope = slack.page.locator(`[data-qa="message_container"]:has-text("${parentText}")`).first();
  }
  await expect(
    scope.locator(`button:has-text("${buttonText}"), [role="button"]:has-text("${buttonText}")`).first()
  ).toBeVisible({ timeout: 10_000 });
}

export async function assertButtonNotVisible(
  slack: SlackPage,
  buttonText: string,
  parentText?: string
): Promise<void> {
  let scope: any = slack.page;
  if (parentText) {
    scope = slack.page.locator(`[data-qa="message_container"]:has-text("${parentText}")`).first();
  }
  await expect(
    scope.locator(`button:has-text("${buttonText}"), [role="button"]:has-text("${buttonText}")`).first()
  ).not.toBeVisible({ timeout: 5_000 });
}

export async function assertDashboardState(
  slack: SlackPage,
  stateLabel: string
): Promise<void> {
  // Dashboard states are reflected in the modal title or a section header
  const modal = slack.page.locator('[data-qa="modal"], .p-block_kit_modal');
  await expect(modal).toContainText(stateLabel, { timeout: 10_000 });
}

export async function assertOrderInRecap(
  slack: SlackPage,
  userName: string,
  description: string
): Promise<void> {
  const modal = slack.page.locator('[data-qa="modal"], .p-block_kit_modal');
  await expect(modal).toContainText(userName, { timeout: 10_000 });
  await expect(modal).toContainText(description, { timeout: 5_000 });
}

export async function assertPriceInRecap(
  slack: SlackPage,
  label: string,
  amount: string
): Promise<void> {
  const modal = slack.page.locator('[data-qa="modal"], .p-block_kit_modal');
  await expect(modal).toContainText(amount, { timeout: 10_000 });
}
