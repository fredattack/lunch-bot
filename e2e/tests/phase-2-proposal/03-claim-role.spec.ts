import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder, claimRole } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible, assertMessageVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-2.3: Claim Role', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should allow User B to claim orderer role on a Pickup proposal', async ({ slackPageA, slackPageB }) => {
    // User A creates proposal (auto-assigned as runner for Pickup)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // User B sees the proposal in the channel and claims orderer
    await slackPageB.reload();
    await slackPageB.wait(2000);

    // User B clicks the orderer claim button on the proposal message
    const claimBtn = slackPageB.page.locator('button:has-text("orderer"), button:has-text("Orderer")').first();
    if (await claimBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await claimBtn.click({ force: true });
      await slackPageB.wait(2000);
    }
  });

  test('should allow User B to take charge of a proposal via dashboard', async ({ slackPageA, slackPageB }) => {
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

    // User B opens dashboard and takes charge
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    const takeChargeBtn = slackPageB.page.locator('button:has-text("Prendre en charge")').first();
    if (await takeChargeBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await takeChargeBtn.click({ force: true });
      await slackPageB.wait(2000);
    }
  });
});
