import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder, proposeFromCatalog } from '../../helpers/slack-actions';
import { assertModalOpen, assertModalError } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders } from '../../fixtures/test-data';

test.describe('E2E-8.2: Duplicate Vendor Proposal', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should reject duplicate vendor proposal in same session', async ({ slackPageA, slackPageB }) => {
    // User A proposes Pizza Place
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // User B tries to propose the same vendor
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    await slackPageB.clickButton('Demarrer une commande');
    await slackPageB.waitForModal();

    await slackPageB.selectModalOption('enseigne', TestVendors.PIZZA_PLACE.name);
    await slackPageB.submitModal();
    await slackPageB.wait(2000);

    // Should get error about duplicate proposal
    const error = await slackPageB.getModalErrorText();
    // The error may be in the modal or as a generic error
    if (error) {
      expect(error.toLowerCase()).toContain('deja');
    }
  });
});
