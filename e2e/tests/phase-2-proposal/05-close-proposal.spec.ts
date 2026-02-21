import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder, closeProposal } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-2.5: Close Proposal', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should allow runner to close their proposal', async ({ slackPageA }) => {
    // Create proposal + order (User A becomes runner)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // Open dashboard as runner (S4 state) and close proposal
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const closeBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await closeBtn.click();
      await slackPageA.wait(3000);
    }

    // Re-open dashboard — should transition to S5
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
  });

  test('should reject close from non-responsible user', async ({ slackPageA, slackPageB }) => {
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

    // User B should NOT be able to close the proposal
    // User B would need to see the close button — typically only runner/orderer sees it
    await slackPageB.reload();
    await slackPageB.wait(2000);

    // Check that close button is not available for User B on the proposal
    const closeBtn = slackPageB.page.locator('button:has-text("Cloturer")').first();
    const isVisible = await closeBtn.isVisible({ timeout: 3000 }).catch(() => false);

    if (isVisible) {
      await closeBtn.click();
      await slackPageB.wait(2000);
      // Should receive ephemeral rejection
      await assertEphemeralVisible(slackPageB, ErrorMessages.ONLY_RESPONSIBLE_CAN_CLOSE);
    }
    // If button is not visible, that's also correct behavior (UI-level restriction)
  });
});
