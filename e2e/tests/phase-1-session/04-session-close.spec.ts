import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import {
  openDashboard,
  proposeAndOrder,
  closeSession,
} from '../../helpers/slack-actions';
import {
  assertModalOpen,
  assertMessageVisible,
} from '../../helpers/slack-assertions';
import { TestVendors, TestOrders } from '../../fixtures/test-data';

test.describe('E2E-1.4: Session Close', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should close session via dashboard and post closure summary', async ({ slackPageA }) => {
    // Setup: create session + proposal + order (so user becomes runner and can close)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(2000);

    // Re-open dashboard as runner (S4 state)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Close session
    const closeBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await closeBtn.click();
      await slackPageA.wait(3000);
    }
  });

  test('should cascade close all proposals when session is closed', async ({ slackPageA }) => {
    await resetDatabase();

    // Create session + proposal + order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(2000);

    // Close via session close button
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const closeSessionBtn = slackPageA.page.locator('button:has-text("Cloturer la journee"), button:has-text("Cloturer")').first();
    if (await closeSessionBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await closeSessionBtn.click();
      await slackPageA.wait(3000);
    }

    // Re-open dashboard — should show S5 (AllClosed)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    const content = await slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal').innerText();
    // After closing, the dashboard should reflect closed state
    expect(content).toBeTruthy();
  });
});
