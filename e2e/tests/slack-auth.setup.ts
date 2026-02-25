import { test as setup, expect, Page } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const AUTH_DIR = path.resolve(__dirname, '../auth');

const AUTH_MAX_AGE_MS = 60 * 60 * 1000; // 1 hour

function isAuthFresh(filePath: string): boolean {
  if (!fs.existsSync(filePath)) {
    return false;
  }
  const stats = fs.statSync(filePath);
  return Date.now() - stats.mtimeMs < AUTH_MAX_AGE_MS;
}

async function dismissCookieBanner(page: Page): Promise<void> {
  const cookieButton = page.locator('button:has-text("ACCEPT ALL COOKIES"), button:has-text("Accept All Cookies")');
  if (await cookieButton.first().isVisible({ timeout: 3000 }).catch(() => false)) {
    await cookieButton.first().click();
  }
}

async function loginToSlack(
  page: Page,
  email: string,
  password: string,
  storagePath: string,
  signinUrl: string,
  workspaceUrl: string
): Promise<void> {
  // Navigate to the workspace-specific sign-in page
  await page.goto(signinUrl);

  await dismissCookieBanner(page);

  // Scenario 1: workspace-signin page (asks for workspace URL)
  const workspaceInput = page.locator('input[data-qa="signin_domain_input"]');
  if (await workspaceInput.isVisible({ timeout: 3000 }).catch(() => false)) {
    // Extract subdomain from signinUrl (e.g. "e0aexp36w5r-9gibjh8n" from "https://e0aexp36w5r-9gibjh8n.slack.com/")
    const subdomain = new URL(signinUrl).hostname.replace('.slack.com', '');
    await workspaceInput.fill(subdomain);
    await page.locator('button:has-text("Continue"), button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
    await dismissCookieBanner(page);
  }

  // Scenario 2: direct email/password login page
  const emailInput = page.getByRole('textbox', { name: 'Email' });
  await emailInput.waitFor({ state: 'visible', timeout: 15_000 });
  await emailInput.fill(email);

  const passwordInput = page.getByRole('textbox', { name: 'Password' });
  await passwordInput.fill(password);

  await page.getByRole('button', { name: 'Sign In' }).click();

  // Wait for Slack to fully load (channel tree visible in sidebar)
  await page.getByRole('tree').first().waitFor({ state: 'visible', timeout: 60_000 });

  // Navigate to the target workspace/channel to ensure correct context
  if (workspaceUrl !== signinUrl) {
    await page.goto(workspaceUrl);
    await page.getByRole('tree').first().waitFor({ state: 'visible', timeout: 30_000 });
  }

  // Persist session
  await page.context().storageState({ path: storagePath });
}

setup('authenticate user A', async ({ page }) => {
  const authFile = path.join(AUTH_DIR, 'user-a.json');
  setup.skip(isAuthFresh(authFile), 'Auth file is fresh');
  await loginToSlack(
    page,
    process.env.SLACK_TEST_USER_A_EMAIL!,
    process.env.SLACK_TEST_USER_A_PASSWORD!,
    authFile,
    process.env.SLACK_WORKSPACE_SIGNIN_URL!,
    process.env.SLACK_WORKSPACE_URL!
  );
});

setup('authenticate user B', async ({ page }) => {
  const authFile = path.join(AUTH_DIR, 'user-b.json');
  setup.skip(isAuthFresh(authFile), 'Auth file is fresh');
  await loginToSlack(
    page,
    process.env.SLACK_TEST_USER_B_EMAIL!,
    process.env.SLACK_TEST_USER_B_PASSWORD!,
    authFile,
    process.env.SLACK_WORKSPACE_SIGNIN_URL!,
    process.env.SLACK_WORKSPACE_URL!
  );
});

setup('authenticate user C', async ({ page }) => {
  const authFile = path.join(AUTH_DIR, 'user-c.json');
  setup.skip(isAuthFresh(authFile), 'Auth file is fresh');
  await loginToSlack(
    page,
    process.env.SLACK_TEST_USER_C_EMAIL!,
    process.env.SLACK_TEST_USER_C_PASSWORD!,
    authFile,
    process.env.SLACK_WORKSPACE_SIGNIN_URL!,
    process.env.SLACK_WORKSPACE_URL!
  );
});

setup('authenticate admin', async ({ page }) => {
  const authFile = path.join(AUTH_DIR, 'admin.json');
  setup.skip(isAuthFresh(authFile), 'Auth file is fresh');
  await loginToSlack(
    page,
    process.env.SLACK_TEST_ADMIN_EMAIL!,
    process.env.SLACK_TEST_ADMIN_PASSWORD!,
    authFile,
    process.env.SLACK_WORKSPACE_SIGNIN_URL!,
    process.env.SLACK_WORKSPACE_URL!
  );
});
