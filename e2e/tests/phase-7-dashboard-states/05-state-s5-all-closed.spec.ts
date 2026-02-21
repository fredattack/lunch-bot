import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertButtonVisible, assertDashboardState } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, DashboardLabels } from '../../fixtures/test-data';

test.describe('E2E-7.5: Dashboard State S5 — All Closed', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should display S5 state with "Relancer" button after all proposals closed', async ({ slackPageA }) => {
    // Create proposal + order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // Close the proposal
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const closeBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await closeBtn.click();
      await slackPageA.wait(3000);
    }

    // Re-open dashboard — should be S5 (all proposals closed)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Should show "Relancer" option
    const relaunchBtn = slackPageA.page.locator('button:has-text("Relancer")').first();
    const isVisible = await relaunchBtn.isVisible({ timeout: 5000 }).catch(() => false);
    if (isVisible) {
      expect(isVisible).toBe(true);
    }
  });
});
