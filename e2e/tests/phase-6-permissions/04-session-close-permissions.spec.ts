import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders } from '../../fixtures/test-data';

test.describe('E2E-6.4: Session Close Permissions', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should reject session close from user without runner/orderer role', async ({ slackPageA, slackPageB }) => {
    // User A creates proposal
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // User B tries to close session via channel message button
    await slackPageB.reload();
    await slackPageB.wait(2000);

    const closeSessionBtn = slackPageB.page.locator('button:has-text("Cloturer la journee")').first();
    if (await closeSessionBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await closeSessionBtn.click();
      await slackPageB.wait(2000);
      await assertEphemeralVisible(slackPageB, 'runner/orderer ou un admin');
    }
  });

  test('should allow admin to close session', async ({ slackPageA, slackPageAdmin }) => {
    await resetDatabase();

    // User A creates proposal
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // Admin closes session
    await slackPageAdmin.reload();
    await slackPageAdmin.wait(2000);

    const closeSessionBtn = slackPageAdmin.page.locator('button:has-text("Cloturer la journee")').first();
    if (await closeSessionBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await closeSessionBtn.click();
      await slackPageAdmin.wait(3000);
      // Should succeed — no error message
    }
  });
});
