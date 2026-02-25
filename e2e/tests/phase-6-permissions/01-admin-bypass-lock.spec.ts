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

    // Lock the session
    await forceSessionLock();

    // Admin opens dashboard and tries to order
    await openDashboard(slackPageAdmin);
    await assertModalOpen(slackPageAdmin);

    // Admin should be able to interact even though session is locked
    await slackPageAdmin.clickButton('Commander ici');
    await placeOrder(slackPageAdmin, 'Admin order on locked session', '20');
    await slackPageAdmin.wait(3000);
  });

  test('should reject regular user from ordering on locked session', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();

    // User A creates session + proposal + order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await placeOrder(slackPageA, TestOrders.MARGHERITA.description, TestOrders.MARGHERITA.priceEstimated);
    await slackPageA.wait(2000);

    // Lock the session
    await forceSessionLock();

    // User B opens dashboard and tries to order
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    await slackPageB.clickButton('Commander ici');
    await slackPageB.wait(2000);
    // Should see ephemeral rejection
    await assertEphemeralVisible(slackPageB, ErrorMessages.ORDERS_LOCKED);
  });
});
