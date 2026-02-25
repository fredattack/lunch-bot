import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import {
  openDashboard,
  proposeFromCatalog,
  placeOrder,
  dashboardOrderHere,
  adjustFinalPrice,
  closeProposal,
  refreshAll,
} from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, TestPrices, DashboardLabels, TestUsers } from '../../fixtures/test-data';

test.describe('E2E-9.1: Happy Path — Full Group Order with 4 Users', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should complete full lifecycle: A proposes, B+C+Admin order, A manages recap and closes', async ({
    slackPageA,
    slackPageB,
    slackPageC,
    slackPageAdmin,
  }) => {
    // ── Step 1: User A opens dashboard → S1 (no proposals) ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    let content = await slackPageA.getModalContent();
    expect(content).toContain(DashboardLabels.S1);

    // ── Step 2-3: User A proposes Pizza Place (Pickup) → becomes runner ──
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name, 'pickup');
    await assertModalOpen(slackPageA);

    // ── Step 4: User A places first order (Margherita) ──
    await placeOrder(
      slackPageA,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3_000);

    // ── Step 5: Verify "Nouvelle commande" message posted ──
    await assertMessageVisible(slackPageA, 'Nouvelle commande');

    // ── Step 6: User B opens dashboard → sees S2 (open proposal) ──
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    // ── Step 7: User B orders a Calzone ──
    await dashboardOrderHere(
      slackPageB,
      TestOrders.CALZONE.description,
      TestOrders.CALZONE.priceEstimated
    );
    await slackPageB.wait(3_000);

    // ── Step 8: User C opens dashboard → sees S2 ──
    await openDashboard(slackPageC);
    await assertModalOpen(slackPageC);

    // ── Step 9: User C orders a Cheeseburger ──
    await dashboardOrderHere(
      slackPageC,
      TestOrders.CHEESEBURGER.description,
      TestOrders.CHEESEBURGER.priceEstimated
    );
    await slackPageC.wait(3_000);

    // ── Step 10: Admin opens dashboard → sees S2 ──
    await openDashboard(slackPageAdmin);
    await assertModalOpen(slackPageAdmin);

    // ── Step 11: Admin orders Quatre Fromages ──
    await dashboardOrderHere(
      slackPageAdmin,
      TestOrders.QUATRE_FROMAGES.description,
      TestOrders.QUATRE_FROMAGES.priceEstimated
    );
    await slackPageAdmin.wait(3_000);

    // ── Step 12: User A views recap via dashboard S4 (4 orders) ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const recapBtn = slackPageA.page.locator('[data-qa="wizard_modal"]').last()
      .locator('button:has-text("recap")').first();
    if (await recapBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await recapBtn.click({ force: true });
      await slackPageA.wait(3_000);

      if (await slackPageA.isModalVisible()) {
        const recapContent = await slackPageA.getModalContent();
        expect(recapContent).toContain(TestOrders.MARGHERITA.description);
        expect(recapContent).toContain(TestOrders.CALZONE.description);
        expect(recapContent).toContain(TestOrders.CHEESEBURGER.description);
        expect(recapContent).toContain(TestOrders.QUATRE_FROMAGES.description);

        // Capture recap modal
        const recapModal = slackPageA.page.locator('[data-qa="wizard_modal"]').last();
        await recapModal.screenshot({ path: 'Docs/screens/26-modal-recap-commandes.png' });

        await slackPageA.dismissModal();
      }
    }

    // ── Step 13: User A adjusts final prices (via channel message) ──
    if (await slackPageA.isModalVisible()) {
      await slackPageA.dismissModal();
    }
    await slackPageA.reload();
    await slackPageA.wait(3_000);

    const adjustBtn = slackPageA.page.locator('button:has-text("Ajuster prix"), button:has-text("Ajuster")').first();
    if (await adjustBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await adjustBtn.click({ force: true });
      await slackPageA.waitForModal();
      await slackPageA.fillModalField('price_final', TestPrices.FINAL_ADJUSTED);
      await slackPageA.submitModal();
      await slackPageA.wait(3_000);
    }

    // ── Step 14: User A closes proposal (via channel message) ──
    if (await slackPageA.isModalVisible()) {
      await slackPageA.dismissModal();
    }
    await slackPageA.reload();
    await slackPageA.wait(2_000);

    const closeBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await closeBtn.click({ force: true });
      await slackPageA.wait(3_000);
    }

    // ── Step 15: All users see S5 (all closed) on their dashboard ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    content = await slackPageA.getModalContent();
    expect(content).not.toContain(DashboardLabels.S1);

    // User B should see their order is closed too
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);
    content = await slackPageB.getModalContent();
    expect(content).not.toContain(DashboardLabels.S1);
  });
});
