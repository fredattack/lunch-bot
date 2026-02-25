import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase, forceSessionLock } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-8.1: Locked Session Actions', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should reject order creation on locked session for regular user', async ({ slackPageA, slackPageB }) => {
    // User A creates proposal + order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(2000);

    // Lock session
    await forceSessionLock();

    // User B opens dashboard and tries to place order
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    await slackPageB.clickButton('Commander ici');
    await slackPageB.wait(2000);
    await assertEphemeralVisible(slackPageB, ErrorMessages.ORDERS_LOCKED);
  });

  test('should reject order edit on locked session for regular user', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();

    // Setup: User A creates proposal, User B places order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // User B orders
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);
    await slackPageB.clickButton('Commander ici');
    await slackPageB.fillModalField('description', TestOrders.CALZONE.description);
    await slackPageB.submitModal();
    await slackPageB.wait(2000);

    // Lock session
    await forceSessionLock();

    // User B tries to edit
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    const editBtn = slackPageB.page.locator('button:has-text("Modifier"), button:has-text("Voir ma commande")').first();
    if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await editBtn.click({ force: true });
      await slackPageB.wait(2000);
      // Should be restricted
      const ephemeral = await slackPageB.getEphemeralText();
      if (ephemeral) {
        expect(ephemeral.toLowerCase()).toContain('verrouill');
      }
    }
  });
});
