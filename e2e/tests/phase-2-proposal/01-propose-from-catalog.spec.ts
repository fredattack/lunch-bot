import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeFromCatalog, placeOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders } from '../../fixtures/test-data';

test.describe('E2E-2.1: Propose from Catalog', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should open proposal modal with vendor list from catalog', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await slackPageA.clickButton('Demarrer une commande');
    await slackPageA.waitForModal();

    // Verify vendor select is visible
    const modal = slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal');
    await expect(modal).toBeVisible();
  });

  test('should create proposal with Pickup fulfillment and auto-assign runner', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name, 'pickup');

    // Modal should transition to order form
    await assertModalOpen(slackPageA);
  });

  test('should create proposal with Delivery fulfillment and auto-assign orderer', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await proposeFromCatalog(slackPageA, TestVendors.SUSHI_BAR.name, 'delivery');

    // Modal should transition to order form
    await assertModalOpen(slackPageA);
  });

  test('should transition to order modal after proposal creation', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await assertModalOpen(slackPageA);

    // Submit an order to confirm the transition worked
    await placeOrder(slackPageA, TestOrders.MARGHERITA.description, TestOrders.MARGHERITA.priceEstimated);
    await slackPageA.wait(2000);

    // Thread message should appear
    await assertMessageVisible(slackPageA, 'Nouvelle commande');
  });
});
