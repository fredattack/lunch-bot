import { test as setup, expect } from '@playwright/test';
import path from 'path';

const AUTH_DIR = path.resolve(__dirname, '../auth');

async function loginToSlack(
  page: ReturnType<typeof setup['info']> extends never ? never : any,
  email: string,
  password: string,
  storagePath: string,
  workspaceUrl: string
): Promise<void> {
  await page.goto(workspaceUrl);

  // Slack login flow: enter email
  const emailInput = page.locator('input[data-qa="login_email"]');
  if (await emailInput.isVisible({ timeout: 5000 }).catch(() => false)) {
    await emailInput.fill(email);
    await page.locator('input[data-qa="login_password"]').fill(password);
    await page.locator('button[data-qa="signin_button"]').click();
  }

  // Wait for Slack to fully load (channel list visible)
  await page.waitForSelector('[data-qa="channel_sidebar_channels_section"]', {
    timeout: 30_000,
  });

  // Persist session
  await page.context().storageState({ path: storagePath });
}

setup('authenticate user A', async ({ page }) => {
  await loginToSlack(
    page,
    process.env.SLACK_TEST_USER_A_EMAIL!,
    process.env.SLACK_TEST_USER_A_PASSWORD!,
    path.join(AUTH_DIR, 'user-a.json'),
    process.env.SLACK_WORKSPACE_URL!
  );
});

setup('authenticate user B', async ({ page }) => {
  await loginToSlack(
    page,
    process.env.SLACK_TEST_USER_B_EMAIL!,
    process.env.SLACK_TEST_USER_B_PASSWORD!,
    path.join(AUTH_DIR, 'user-b.json'),
    process.env.SLACK_WORKSPACE_URL!
  );
});

setup('authenticate admin', async ({ page }) => {
  await loginToSlack(
    page,
    process.env.SLACK_TEST_ADMIN_EMAIL!,
    process.env.SLACK_TEST_ADMIN_PASSWORD!,
    path.join(AUTH_DIR, 'admin.json'),
    process.env.SLACK_WORKSPACE_URL!
  );
});
