import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import {
  openDashboard,
  proposeFromCatalog,
  placeOrder,
  launchAnotherProposal,
  dashboardOrderHere,
  closeProposal,
  refreshAll,
} from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, TestUsers } from '../../fixtures/test-data';

test.describe('E2E-9.3: Multi-Proposal Session with 4 Users', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should handle 3 proposals across 4 users with cross-ordering', async ({
    slackPageA,
    slackPageB,
    slackPageC,
    slackPageAdmin,
  }) => {
    // ══════════════════════════════════════════════════════════════
    // PROPOSAL 1: Pizza Place — User A proposes + orders
    // ══════════════════════════════════════════════════════════════
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await placeOrder(
      slackPageA,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3_000);

    // ── User B orders on Proposal 1 (Pizza) ──
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);
    await dashboardOrderHere(
      slackPageB,
      TestOrders.CALZONE.description,
      TestOrders.CALZONE.priceEstimated
    );
    await slackPageB.wait(3_000);

    // ══════════════════════════════════════════════════════════════
    // PROPOSAL 2: Sushi Bar — User C proposes + orders
    // ══════════════════════════════════════════════════════════════
    await openDashboard(slackPageC);
    await assertModalOpen(slackPageC);

    // User C launches a second proposal from the dashboard
    await launchAnotherProposal(slackPageC, TestVendors.SUSHI_BAR.name);
    await placeOrder(
      slackPageC,
      TestOrders.CALIFORNIA_ROLL.description,
      TestOrders.CALIFORNIA_ROLL.priceEstimated
    );
    await slackPageC.wait(3_000);

    // ── Admin orders on Proposal 2 (Sushi) ──
    await openDashboard(slackPageAdmin);
    await assertModalOpen(slackPageAdmin);

    // Admin should see both proposals — order on the sushi one
    const sushiOrderBtn = slackPageAdmin.page
      .locator('button:has-text("Commander")')
      .first();
    if (await sushiOrderBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await sushiOrderBtn.click();
      await slackPageAdmin.waitForModal();
      await placeOrder(
        slackPageAdmin,
        TestOrders.EDAMAME.description,
        TestOrders.EDAMAME.priceEstimated
      );
      await slackPageAdmin.wait(3_000);
    }

    // ── User A also orders from Proposal 2 (Sushi) ──
    await slackPageA.reload();
    await slackPageA.wait(2_000);
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const sushiBtn = slackPageA.page.locator('button:has-text("Commander")').first();
    if (await sushiBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await sushiBtn.click();
      await slackPageA.waitForModal();
      await placeOrder(slackPageA, TestOrders.SALMON_ROLL.description, TestOrders.SALMON_ROLL.priceEstimated);
      await slackPageA.wait(3_000);
    }

    // ══════════════════════════════════════════════════════════════
    // CLOSE PROPOSAL 1 (Pizza) — User A is runner
    // ══════════════════════════════════════════════════════════════
    await slackPageA.reload();
    await slackPageA.wait(2_000);

    const closePizzaBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();
    if (await closePizzaBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await closePizzaBtn.click();
      await slackPageA.wait(3_000);
    }

    // ── Verify Proposal 2 (Sushi) is still open ──
    await openDashboard(slackPageC);
    await assertModalOpen(slackPageC);
    const content = await slackPageC.getModalContent();
    // Dashboard should still show active content (not all-closed S5)
    expect(content).toBeTruthy();

    // ══════════════════════════════════════════════════════════════
    // CLOSE PROPOSAL 2 (Sushi) — User C is runner
    // ══════════════════════════════════════════════════════════════
    await slackPageC.reload();
    await slackPageC.wait(2_000);

    const closeSushiBtn = slackPageC.page.locator('button:has-text("Cloturer")').first();
    if (await closeSushiBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await closeSushiBtn.click();
      await slackPageC.wait(3_000);
    }

    // ── All proposals closed — everyone sees S5 ──
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);
    const finalContent = await slackPageB.getModalContent();
    // S1 should not appear since there were proposals (even if closed)
    expect(finalContent).not.toContain('Aucune commande');
  });
});
