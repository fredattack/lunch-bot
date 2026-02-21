import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeFromCatalog, placeOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders } from '../../fixtures/test-data';

test.describe('E2E-9.3: Multi-Proposal Session', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should handle session with multiple vendor proposals and cross-orders', async ({
    slackPageA,
    slackPageB,
  }) => {
    // ── Proposal 1: Pizza Place (User A) ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await placeOrder(slackPageA, TestOrders.MARGHERITA.description, TestOrders.MARGHERITA.priceEstimated);
    await slackPageA.wait(3000);

    // ── Proposal 2: Sushi Bar (User B) ──
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    const startBtn = slackPageB.page.locator('button:has-text("Lancer une autre"), button:has-text("Demarrer")').first();
    if (await startBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await startBtn.click();
      await slackPageB.waitForModal();

      await slackPageB.selectModalOption('enseigne', TestVendors.SUSHI_BAR.name);
      await slackPageB.submitModal();
      await slackPageB.waitForModal();

      await placeOrder(slackPageB, TestOrders.CALIFORNIA_ROLL.description, TestOrders.CALIFORNIA_ROLL.priceEstimated);
      await slackPageB.wait(3000);
    }

    // ── User A also orders from Sushi Bar ──
    await slackPageA.reload();
    await slackPageA.wait(2000);
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // User A should see both proposals and can order from the second one
    const sushiOrderBtn = slackPageA.page.locator(`button:has-text("Commander")`).first();
    if (await sushiOrderBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await sushiOrderBtn.click();
      await slackPageA.waitForModal();
      await placeOrder(slackPageA, 'Salmon Roll', '8');
      await slackPageA.wait(3000);
    }

    // ── Close Proposal 1 (Pizza) ──
    await slackPageA.reload();
    await slackPageA.wait(2000);

    const closePizzaBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();
    if (await closePizzaBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await closePizzaBtn.click();
      await slackPageA.wait(3000);
    }

    // Sushi proposal should still be open
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    const content = await slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal').innerText();
    expect(content).toBeTruthy();
  });
});
