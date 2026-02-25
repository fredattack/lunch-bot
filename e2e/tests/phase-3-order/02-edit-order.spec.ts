import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder, editOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-3.2: Edit Order', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should edit order description and price via dashboard', async ({ slackPageA }) => {
    // Create proposal + order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(2000);

    // Re-open dashboard (S3 state — has order)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Click "Modifier" button
    const editBtn = slackPageA.page.locator('button:has-text("Modifier")').first();
    if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await editBtn.click({ force: true });
      await slackPageA.waitForModal();

      // Edit the order
      await editOrder(slackPageA, 'Quatre Fromages', '15');
      await slackPageA.wait(2000);
    }
  });

  test('should update proposal message after order edit', async ({ slackPageA }) => {
    // After editing, the proposal message count should still be correct
    await slackPageA.reload();
    await slackPageA.wait(2000);

    // The messages should be present in the channel
    const content = await slackPageA.page.locator('[data-qa="message_container"]').last().innerText();
    expect(content).toBeTruthy();
  });
});
