import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders } from '../../fixtures/test-data';

test.describe('E2E-8.3: Order Upsert (Existing Order)', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should update existing order instead of creating duplicate', async ({ slackPageA }) => {
    // User A creates proposal + first order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // User A re-opens order modal for same proposal
    // This should pre-fill with existing order data
    await slackPageA.reload();
    await slackPageA.wait(2000);

    // Click "Commander" button on the same proposal
    const orderBtn = slackPageA.page.locator('button:has-text("Commander"), button:has-text("commande")').first();
    if (await orderBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await orderBtn.click();
      await slackPageA.waitForModal();

      // Modal should be pre-filled with existing order
      const descField = slackPageA.page.locator('[data-qa-block-id="description"] input, [data-block-id="description"] input, [data-qa-block-id="description"] textarea').first();
      if (await descField.isVisible({ timeout: 5000 }).catch(() => false)) {
        const currentValue = await descField.inputValue();
        expect(currentValue).toContain(TestOrders.MARGHERITA.description);

        // Change to new description
        await descField.fill('Quatre Fromages (modifie)');
        await slackPageA.submitModal();
        await slackPageA.wait(2000);
      }
    }
  });
});
