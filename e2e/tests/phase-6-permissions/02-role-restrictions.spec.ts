import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-6.2: Role Restrictions', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should reject non-runner from closing proposal', async ({ slackPageA, slackPageB }) => {
    // User A creates proposal (becomes runner)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // User B tries to close
    await slackPageB.reload();
    await slackPageB.wait(2000);

    const closeBtn = slackPageB.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await closeBtn.click();
      await slackPageB.wait(2000);
      await assertEphemeralVisible(slackPageB, ErrorMessages.ONLY_RESPONSIBLE_CAN_CLOSE);
    }
  });

  test('should reject non-runner from viewing recap', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();

    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // User B tries to view recap
    await slackPageB.reload();
    await slackPageB.wait(2000);

    const recapBtn = slackPageB.page.locator('button:has-text("Recap"), button:has-text("recapitulatif")').first();
    if (await recapBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await recapBtn.click();
      await slackPageB.wait(2000);
      await assertEphemeralVisible(slackPageB, ErrorMessages.ONLY_RESPONSIBLE_CAN_VIEW);
    }
  });

  test('should reject delegation from non-role-holder', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();

    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // User B tries to delegate
    await slackPageB.reload();
    await slackPageB.wait(2000);

    const delegateBtn = slackPageB.page.locator('button:has-text("Deleguer")').first();
    if (await delegateBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await delegateBtn.click();
      await slackPageB.wait(2000);
      await assertEphemeralVisible(slackPageB, ErrorMessages.NO_ROLE_TO_DELEGATE);
    }
  });
});
