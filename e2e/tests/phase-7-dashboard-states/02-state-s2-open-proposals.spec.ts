import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertButtonVisible, assertDashboardState } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, DashboardLabels } from '../../fixtures/test-data';

test.describe('E2E-7.2: Dashboard State S2 — Open Proposals, No Order', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should display S2 state for user without order when proposals exist', async ({ slackPageA, slackPageB }) => {
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

    // User B opens dashboard — should see S2 (proposals exist, no order yet)
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    // Should show proposal list with order buttons
    const modal = slackPageB.page.locator('[data-qa="wizard_modal"]').last();
    const content = await modal.innerText();
    expect(content).toContain(TestVendors.PIZZA_PLACE.name);

    // Capture Dashboard S2
    await modal.screenshot({ path: 'Docs/screens/18-dashboard-s2-open-proposals.png' });
  });

  test('should show "Commander ici" button for each proposal in S2', async ({ slackPageA, slackPageB }) => {
    // User B should see order button
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);
    await assertButtonVisible(slackPageB, 'Commander');
  });
});
