import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertDashboardState } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, DashboardLabels } from '../../fixtures/test-data';

test.describe('E2E-7.3: Dashboard State S3 — Has Order', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should display S3 state showing order details when user has an order', async ({ slackPageA, slackPageB }) => {
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

    // User B places an order from the existing proposal
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);
    await slackPageB.clickButton('Commander ici');
    await slackPageB.fillModalField('description', TestOrders.CALZONE.description);
    await slackPageB.fillModalField('price_estimated', TestOrders.CALZONE.priceEstimated);
    await slackPageB.submitModal();
    await slackPageB.wait(3000);

    // User B re-opens dashboard — should be in S3 (has order)
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    const modal = slackPageB.page.locator('[data-qa="wizard_modal"]').last();
    const content = await modal.innerText();
    // Should show the user's order details
    expect(content).toContain(TestOrders.CALZONE.description);
  });
});
