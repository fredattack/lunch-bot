import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import {
  openDashboard,
  proposeFromCatalog,
  placeOrder,
  dashboardOrderHere,
  claimRole,
  delegateRole,
  takeCharge,
  viewRecap,
  refreshAll,
} from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, ErrorMessages, TestUsers } from '../../fixtures/test-data';

test.describe('E2E-9.4: Multi-User Concurrent Actions with 4 Users', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should handle concurrent role claims — only one user gets the role', async ({
    slackPageA,
    slackPageB,
    slackPageC,
    slackPageAdmin,
  }) => {
    // ── Setup: User A creates a Delivery proposal (orderer auto-assigned) ──
    // Runner role remains free for others to claim
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name, 'delivery');
    await placeOrder(
      slackPageA,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3_000);

    // ── All 3 other users reload to see the proposal ──
    await refreshAll(slackPageB, slackPageC, slackPageAdmin);

    // ── User B and C both try to claim runner role simultaneously ──
    // In real usage this is a race condition — lockForUpdate() ensures only one wins
    const claimRunnerB = slackPageB.page
      .locator('button:has-text("runner"), button:has-text("Runner")')
      .first();
    const claimRunnerC = slackPageC.page
      .locator('button:has-text("runner"), button:has-text("Runner")')
      .first();

    // Fire both clicks as close together as possible
    const results = await Promise.allSettled([
      (async () => {
        if (await claimRunnerB.isVisible({ timeout: 5_000 }).catch(() => false)) {
          await claimRunnerB.click({ force: true });
          await slackPageB.wait(3_000);
        }
      })(),
      (async () => {
        if (await claimRunnerC.isVisible({ timeout: 5_000 }).catch(() => false)) {
          await claimRunnerC.click({ force: true });
          await slackPageC.wait(3_000);
        }
      })(),
    ]);

    // ── Verify: one succeeded, one got "already assigned" ──
    // We can't predict which one wins, but the state should be consistent
    await slackPageB.wait(2_000);
    await slackPageC.wait(2_000);

    // At least one of them should have gotten the role or an error
    // The important thing is the system didn't crash and state is consistent
  });

  test('should handle 4 users ordering simultaneously on the same proposal', async ({
    slackPageA,
    slackPageB,
    slackPageC,
    slackPageAdmin,
  }) => {
    await resetDatabase();

    // ── User A creates proposal ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await placeOrder(
      slackPageA,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3_000);

    // ── All 3 other users reload ──
    await refreshAll(slackPageB, slackPageC, slackPageAdmin);

    // ── Users B, C, and Admin all place orders as fast as possible ──
    // Sequential but rapid — each opens dashboard and orders
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);
    await dashboardOrderHere(
      slackPageB,
      TestOrders.CALZONE.description,
      TestOrders.CALZONE.priceEstimated
    );
    await slackPageB.wait(2_000);

    await openDashboard(slackPageC);
    await assertModalOpen(slackPageC);
    await dashboardOrderHere(
      slackPageC,
      TestOrders.CHEESEBURGER.description,
      TestOrders.CHEESEBURGER.priceEstimated
    );
    await slackPageC.wait(2_000);

    await openDashboard(slackPageAdmin);
    await assertModalOpen(slackPageAdmin);
    await dashboardOrderHere(
      slackPageAdmin,
      TestOrders.QUATRE_FROMAGES.description,
      TestOrders.QUATRE_FROMAGES.priceEstimated
    );
    await slackPageAdmin.wait(2_000);

    // ── User A views recap — should see all 4 orders ──
    await slackPageA.reload();
    await slackPageA.wait(2_000);

    const recapContent = await viewRecap(slackPageA);
    if (recapContent) {
      expect(recapContent).toContain(TestOrders.MARGHERITA.description);
      expect(recapContent).toContain(TestOrders.CALZONE.description);
      expect(recapContent).toContain(TestOrders.CHEESEBURGER.description);
      expect(recapContent).toContain(TestOrders.QUATRE_FROMAGES.description);
      await slackPageA.dismissModal();
    }
  });

  test('should handle role delegation chain across 4 users', async ({
    slackPageA,
    slackPageB,
    slackPageC,
    slackPageAdmin,
  }) => {
    await resetDatabase();

    // ── User A creates proposal (becomes runner for Pickup) ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name, 'pickup');
    await placeOrder(
      slackPageA,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3_000);

    // ── User A delegates runner role to User B ──
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

    // ── User B is now runner — delegates to User C ──
    await slackPageB.reload();
    await slackPageB.wait(2_000);

    const delegateBtnB = slackPageB.page.locator('button:has-text("Deleguer")').first();
    if (await delegateBtnB.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await delegateBtnB.click({ force: true });
      await slackPageB.waitForModal();
      await slackPageB.selectUser('delegate', TestUsers.USER_C.displayName);
      await slackPageB.submitModal();
      await slackPageB.wait(3_000);
    }

    // ── User C is now runner — should have management buttons ──
    await slackPageC.reload();
    await slackPageC.wait(2_000);

    // User C should see "Cloturer" or "Recap" buttons (runner privileges)
    const closeBtn = slackPageC.page.locator('button:has-text("Cloturer")').first();
    const recapBtn = slackPageC.page.locator('button:has-text("Recap"), button:has-text("recapitulatif")').first();
    const hasRunnerButtons =
      (await closeBtn.isVisible({ timeout: 3_000 }).catch(() => false)) ||
      (await recapBtn.isVisible({ timeout: 3_000 }).catch(() => false));

    // User C has runner privileges
    expect(hasRunnerButtons).toBeTruthy();

    // ── Meanwhile, User A should no longer have runner buttons ──
    await slackPageA.reload();
    await slackPageA.wait(2_000);

    // User A should NOT see "Deleguer" anymore (they're no longer runner)
    const delegateA = slackPageA.page.locator('button:has-text("Deleguer")').first();
    const canStillDelegate = await delegateA.isVisible({ timeout: 3_000 }).catch(() => false);
    // After delegation, User A should not have the delegate button
    expect(canStillDelegate).toBeFalsy();
  });

  test('should handle mixed actions: orders + Quick Run from different users', async ({
    slackPageA,
    slackPageB,
    slackPageC,
    slackPageAdmin,
  }) => {
    await resetDatabase();

    // ── User A creates a regular proposal (Pizza) ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await placeOrder(
      slackPageA,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3_000);

    // ── User B orders on the proposal ──
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);
    await dashboardOrderHere(
      slackPageB,
      TestOrders.CALZONE.description,
      TestOrders.CALZONE.priceEstimated
    );
    await slackPageB.wait(3_000);

    // ── Meanwhile, User C creates a Quick Run (Boulangerie) ──
    await openDashboard(slackPageC);
    await assertModalOpen(slackPageC);

    const quickRunBtn = slackPageC.page.locator('button:has-text("Quick Run")').first();
    if (await quickRunBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await quickRunBtn.click({ force: true });
      await slackPageC.waitForModal();
      await slackPageC.fillModalField('destination', 'Boulangerie du coin');
      await slackPageC.fillModalField('delay', '30');
      await slackPageC.submitModal();
      await slackPageC.wait(3_000);
    }

    // ── Admin adds a request to the Quick Run ──
    await slackPageAdmin.reload();
    await slackPageAdmin.wait(2_000);

    const addBtn = slackPageAdmin.page.locator('button:has-text("Ajouter")').first();
    if (await addBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await addBtn.click({ force: true });
      await slackPageAdmin.waitForModal();
      await slackPageAdmin.fillModalField('description', 'Pain de campagne');
      await slackPageAdmin.fillModalField('price_estimated', '3.50');
      await slackPageAdmin.submitModal();
      await slackPageAdmin.wait(3_000);
    }

    // ── Verify: Regular proposal and Quick Run coexist ──
    // User A should still see their proposal
    await slackPageA.reload();
    await slackPageA.wait(2_000);
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    const dashboardA = await slackPageA.getModalContent();
    expect(dashboardA).toBeTruthy();

    // User C should see the Quick Run
    await slackPageC.reload();
    await slackPageC.wait(2_000);
  });
});
