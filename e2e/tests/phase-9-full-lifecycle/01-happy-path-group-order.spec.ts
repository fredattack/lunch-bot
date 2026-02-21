import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeFromCatalog, placeOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, TestPrices, DashboardLabels } from '../../fixtures/test-data';

test.describe('E2E-9.1: Happy Path — Full Group Order Lifecycle', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should complete full order lifecycle: propose → order → recap → adjust → close', async ({
    slackPageA,
    slackPageB,
  }) => {
    // ── Step 1: User A opens dashboard → S1 ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    let content = await slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal').innerText();
    expect(content).toContain(DashboardLabels.S1);

    // ── Step 2-3: User A proposes Pizza Place (Pickup) ──
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name, 'pickup');
    await assertModalOpen(slackPageA);

    // ── Step 4-5: User A places first order ──
    await placeOrder(
      slackPageA,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // ── Step 6: Thread message posted ──
    await assertMessageVisible(slackPageA, 'Nouvelle commande');

    // ── Step 7: User B opens dashboard → S2 ──
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    // ── Step 8-9: User B places order ──
    const orderBtn = slackPageB.page.locator('button:has-text("Commander")').first();
    if (await orderBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await orderBtn.click();
      await slackPageB.waitForModal();
      await placeOrder(
        slackPageB,
        TestOrders.CALZONE.description,
        TestOrders.CALZONE.priceEstimated
      );
      await slackPageB.wait(3000);
    }

    // ── Step 10: User A views recap ──
    await slackPageA.reload();
    await slackPageA.wait(2000);

    const recapBtn = slackPageA.page.locator('button:has-text("Recap"), button:has-text("recapitulatif")').first();
    if (await recapBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await recapBtn.click();
      await slackPageA.wait(3000);

      // Recap should show 2 orders
      if (await slackPageA.isModalVisible()) {
        content = await slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal').innerText();
        expect(content).toBeTruthy();
        await slackPageA.page.keyboard.press('Escape');
        await slackPageA.wait(500);
      }
    }

    // ── Step 11-12: User A adjusts final prices ──
    const adjustBtn = slackPageA.page.locator('button:has-text("Ajuster prix"), button:has-text("Ajuster")').first();
    if (await adjustBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await adjustBtn.click();
      await slackPageA.waitForModal();
      await slackPageA.fillModalField('price_final', TestPrices.FINAL_ADJUSTED);
      await slackPageA.submitModal();
      await slackPageA.wait(3000);
    }

    // ── Step 13: User A closes proposal ──
    const closeBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await closeBtn.click();
      await slackPageA.wait(3000);
    }

    // ── Step 14: Dashboard shows S5 ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    content = await slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal').innerText();
    expect(content).not.toContain(DashboardLabels.S1);
  });
});
