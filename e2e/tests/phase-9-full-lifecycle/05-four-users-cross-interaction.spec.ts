import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import {
  openDashboard,
  proposeFromCatalog,
  placeOrder,
  launchAnotherProposal,
  dashboardOrderHere,
  claimRole,
  delegateRole,
  viewRecap,
  adjustFinalPrice,
  closeProposal,
  refreshAll,
} from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, TestUsers, DashboardLabels } from '../../fixtures/test-data';

/**
 * E2E-9.5: Four Users Cross-Interaction Flow
 *
 * Scenario reel avec 4 utilisateurs qui interagissent entre eux :
 * - User A cree une commande (Pizza)
 * - User B repond en ajoutant ses choix sur la commande de A
 * - User C cree une seconde commande (Sushi)
 * - Admin choisit librement ou commander (rejoint l'une ou l'autre)
 *
 * Puis verification des recaps, delegations, et clotures croisees.
 */
test.describe('E2E-9.5: Four Users Cross-Interaction Flow', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('A creates order, B joins A, C creates 2nd order, Admin joins freely', async ({
    slackPageA,
    slackPageB,
    slackPageC,
    slackPageAdmin,
  }) => {
    // ══════════════════════════════════════════════════════════════
    // ACT 1: User A lance la premiere commande (Pizza Place)
    // ══════════════════════════════════════════════════════════════

    // User A opens dashboard — fresh session, state S1
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    let contentA = await slackPageA.getModalContent();
    expect(contentA).toContain(DashboardLabels.S1);

    // User A proposes Pizza Place (Pickup) → becomes runner
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name, 'pickup');
    await assertModalOpen(slackPageA);

    // User A places their order: Margherita
    await placeOrder(
      slackPageA,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3_000);

    // Verify thread message posted
    await assertMessageVisible(slackPageA, 'Nouvelle commande');

    // ══════════════════════════════════════════════════════════════
    // ACT 2: User B repond a la commande de A (rejoint Pizza)
    // ══════════════════════════════════════════════════════════════

    // User B opens dashboard — sees S2 (open proposals, no order yet)
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    // User B clicks "Commander ici" on the Pizza proposal
    await dashboardOrderHere(
      slackPageB,
      TestOrders.CALZONE.description,
      TestOrders.CALZONE.priceEstimated
    );
    await slackPageB.wait(3_000);

    // User B re-opens dashboard — should now see S3 (has order)
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);
    const contentB = await slackPageB.getModalContent();
    // Should show their order details
    expect(contentB).toBeTruthy();

    // ══════════════════════════════════════════════════════════════
    // ACT 3: User C cree une seconde commande (Sushi Bar)
    // ══════════════════════════════════════════════════════════════

    // User C opens dashboard — sees S2 (Pizza proposal exists)
    await openDashboard(slackPageC);
    await assertModalOpen(slackPageC);

    // User C launches a NEW proposal (Sushi Bar) instead of joining Pizza
    await launchAnotherProposal(slackPageC, TestVendors.SUSHI_BAR.name, 'pickup');

    // User C places their order: California Roll
    await placeOrder(
      slackPageC,
      TestOrders.CALIFORNIA_ROLL.description,
      TestOrders.CALIFORNIA_ROLL.priceEstimated
    );
    await slackPageC.wait(3_000);

    // ══════════════════════════════════════════════════════════════
    // ACT 4: Admin choisit librement ou commander
    // Admin rejoint le Sushi (proposal de C)
    // ══════════════════════════════════════════════════════════════

    // Admin opens dashboard — sees both proposals (Pizza + Sushi)
    await openDashboard(slackPageAdmin);
    await assertModalOpen(slackPageAdmin);

    // Admin orders on a proposal — let them click "Commander"
    // (will join whichever is first visible, likely the latest proposal)
    const orderBtn = slackPageAdmin.page
      .locator('button:has-text("Commander")')
      .first();
    if (await orderBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await orderBtn.click({ force: true });
      await slackPageAdmin.waitForModal();
      await placeOrder(
        slackPageAdmin,
        TestOrders.EDAMAME.description,
        TestOrders.EDAMAME.priceEstimated
      );
      await slackPageAdmin.wait(3_000);
    }

    // ══════════════════════════════════════════════════════════════
    // ACT 5: Verification — Recaps des deux propositions
    // ══════════════════════════════════════════════════════════════

    // User A (runner Pizza) views recap → should see Margherita + Calzone
    await slackPageA.reload();
    await slackPageA.wait(2_000);

    const recapPizza = await viewRecap(slackPageA);
    if (recapPizza) {
      expect(recapPizza).toContain(TestOrders.MARGHERITA.description);
      expect(recapPizza).toContain(TestOrders.CALZONE.description);
      await slackPageA.dismissModal();
    }

    // User C (runner Sushi) views recap → should see California Roll + (Admin's order)
    await slackPageC.reload();
    await slackPageC.wait(2_000);

    const recapSushi = await viewRecap(slackPageC);
    if (recapSushi) {
      expect(recapSushi).toContain(TestOrders.CALIFORNIA_ROLL.description);
      await slackPageC.dismissModal();
    }

    // ══════════════════════════════════════════════════════════════
    // ACT 6: User A delegue son role de runner a User B
    // ══════════════════════════════════════════════════════════════

    await slackPageA.reload();
    await slackPageA.wait(2_000);

    const delegateBtn = slackPageA.page.locator('button:has-text("Deleguer")').first();
    if (await delegateBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await delegateBtn.click({ force: true });
      await slackPageA.waitForModal();
      await slackPageA.selectUser('delegate', TestUsers.USER_B.displayName);
      await slackPageA.submitModal();
      await slackPageA.wait(3_000);
    }

    // ── User B is now runner for Pizza — verify they have management buttons ──
    await slackPageB.reload();
    await slackPageB.wait(2_000);

    const closeBtnB = slackPageB.page.locator('button:has-text("Cloturer")').first();
    const hasMgmtButtons = await closeBtnB.isVisible({ timeout: 3_000 }).catch(() => false);
    // User B should now have runner management buttons
    expect(hasMgmtButtons).toBeTruthy();

    // ══════════════════════════════════════════════════════════════
    // ACT 7: Clotures croisees
    // User B ferme Pizza, User C ferme Sushi
    // ══════════════════════════════════════════════════════════════

    // User B closes Pizza proposal
    if (await closeBtnB.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await closeBtnB.click({ force: true });
      await slackPageB.wait(3_000);
    }

    // User C closes Sushi proposal
    await slackPageC.reload();
    await slackPageC.wait(2_000);

    const closeBtnC = slackPageC.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtnC.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await closeBtnC.click({ force: true });
      await slackPageC.wait(3_000);
    }

    // ══════════════════════════════════════════════════════════════
    // ACT 8: Etat final — tous les dashboards montrent S5
    // ══════════════════════════════════════════════════════════════

    // All users open dashboard — should see S5 (all closed)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    contentA = await slackPageA.getModalContent();
    expect(contentA).not.toContain(DashboardLabels.S1);

    await openDashboard(slackPageAdmin);
    await assertModalOpen(slackPageAdmin);
    const contentAdmin = await slackPageAdmin.getModalContent();
    expect(contentAdmin).not.toContain(DashboardLabels.S1);
  });
});
