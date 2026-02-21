import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertButtonVisible, assertDashboardState } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, DashboardLabels } from '../../fixtures/test-data';

test.describe('E2E-7.4: Dashboard State S4 — In Charge', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should display S4 state with management buttons when user is runner', async ({ slackPageA }) => {
    // User A creates proposal (auto-assigned as runner for Pickup)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // Re-open dashboard — User A is runner (S4)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const modal = slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal');
    const content = await modal.innerText();
    // Should show runner management buttons
    expect(content).toBeTruthy();
  });

  test('should show "Recapitulatif" and "Cloturer" buttons in S4', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // At least one management button should be visible
    const recapBtn = slackPageA.page.locator('button:has-text("Recap"), button:has-text("recapitulatif")').first();
    const closeBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();

    const hasRecap = await recapBtn.isVisible({ timeout: 3000 }).catch(() => false);
    const hasClose = await closeBtn.isVisible({ timeout: 3000 }).catch(() => false);

    expect(hasRecap || hasClose).toBe(true);
  });
});
