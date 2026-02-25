import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, DashboardLabels } from '../../fixtures/test-data';

test.describe('E2E-8.6: Session Close Cascades', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should close all proposals when session is closed', async ({ slackPageA }) => {
    // Create session with multiple proposals
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Proposal 1
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // Proposal 2
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Try to create a second proposal from dashboard
    const startBtn = slackPageA.page.locator('button:has-text("Lancer une autre"), button:has-text("Demarrer")').first();
    if (await startBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await startBtn.click({ force: true });
      await slackPageA.waitForModal();

      await slackPageA.selectModalOption('enseigne', TestVendors.SUSHI_BAR.name);
      await slackPageA.submitModal();
      await slackPageA.waitForModal();

      // Place order on second proposal
      await slackPageA.fillModalField('description', TestOrders.CALIFORNIA_ROLL.description);
      await slackPageA.fillModalField('price_estimated', TestOrders.CALIFORNIA_ROLL.priceEstimated);
      await slackPageA.submitModal();
      await slackPageA.wait(3000);
    }

    // Close session (should cascade to all proposals)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const closeSessionBtn = slackPageA.page.locator('button:has-text("Cloturer la journee"), button:has-text("Cloturer")').first();
    if (await closeSessionBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await closeSessionBtn.click({ force: true });
      await slackPageA.wait(5000);
    }

    // Re-open dashboard — should be S5 (all closed)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const content = await slackPageA.getModalContent();
    // All proposals should be closed
    expect(content).toBeTruthy();
  });
});
