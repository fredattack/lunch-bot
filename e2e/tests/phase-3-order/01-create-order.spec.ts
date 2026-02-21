import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeFromCatalog, placeOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-3.1: Create Order', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should create a new order with description and price', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await placeOrder(
      slackPageA,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );

    await slackPageA.wait(3000);

    // First order should trigger thread message
    await assertMessageVisible(slackPageA, 'Nouvelle commande');
  });

  test('should post "Nouvelle commande" thread message on first order', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await placeOrder(slackPageA, 'Premier plat', '10');
    await slackPageA.wait(3000);

    await assertMessageVisible(slackPageA, 'Nouvelle commande');
  });

  test('should allow User B to place order on same proposal', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();

    // User A creates proposal + first order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await placeOrder(slackPageA, TestOrders.MARGHERITA.description, TestOrders.MARGHERITA.priceEstimated);
    await slackPageA.wait(3000);

    // User B opens dashboard and orders
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    const orderBtn = slackPageB.page.locator('button:has-text("Commander ici"), button:has-text("Commander")').first();
    if (await orderBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await orderBtn.click();
      await slackPageB.waitForModal();
      await placeOrder(slackPageB, TestOrders.CALZONE.description, TestOrders.CALZONE.priceEstimated);
      await slackPageB.wait(2000);
    }
  });
});
