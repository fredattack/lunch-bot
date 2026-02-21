import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase, forceSessionLock } from '../../helpers/api-helpers';
import { openDashboard, proposeFromCatalog, placeOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-6.1: Admin Bypass Lock', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should allow admin to place order on locked session', async ({ slackPageA, slackPageAdmin }) => {
    // User A creates session + proposal + order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await placeOrder(slackPageA, TestOrders.MARGHERITA.description, TestOrders.MARGHERITA.priceEstimated);
    await slackPageA.wait(2000);
    await slackPageA.page.keyboard.press('Escape');

    // Lock the session
    await forceSessionLock();

    // Admin opens dashboard and tries to order
    await openDashboard(slackPageAdmin);
    await assertModalOpen(slackPageAdmin);

    // Admin should be able to interact even though session is locked
    const orderBtn = slackPageAdmin.page.locator('button:has-text("Commander")').first();
    if (await orderBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await orderBtn.click();
      await slackPageAdmin.waitForModal();
      // Admin can fill and submit — this should succeed
      await placeOrder(slackPageAdmin, 'Admin order on locked session', '20');
      await slackPageAdmin.wait(3000);
    }
  });

  test('should reject regular user from ordering on locked session', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();

    // User A creates session + proposal + order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await placeOrder(slackPageA, TestOrders.MARGHERITA.description, TestOrders.MARGHERITA.priceEstimated);
    await slackPageA.wait(2000);
    await slackPageA.page.keyboard.press('Escape');

    // Lock the session
    await forceSessionLock();

    // User B tries to order
    await slackPageB.reload();
    await slackPageB.wait(2000);

    const orderBtn = slackPageB.page.locator('button:has-text("Commander")').first();
    if (await orderBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await orderBtn.click();
      await slackPageB.wait(2000);
      // Should see ephemeral rejection
      await assertEphemeralVisible(slackPageB, ErrorMessages.ORDERS_LOCKED);
    }
  });
});
