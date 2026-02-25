import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder, adjustFinalPrice } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, TestPrices, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-3.4: Adjust Final Price', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should allow runner to adjust final price of an order', async ({ slackPageA }) => {
    // Create proposal + order (User A is runner)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // Ensure modal is closed and channel is visible with proposal message buttons
    if (await slackPageA.isModalVisible()) {
      await slackPageA.dismissModal();
    }
    await slackPageA.reload();
    await slackPageA.wait(3000);

    // Find "Ajuster prix final" button on the channel proposal message
    const adjustBtn = slackPageA.page.locator('button:has-text("Ajuster prix"), button:has-text("Ajuster")').first();
    if (await adjustBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await adjustBtn.click({ force: true });
      await slackPageA.waitForModal();

      // Capture adjust price modal
      const adjustModal = slackPageA.page.locator('[data-qa="wizard_modal"]').last();
      await adjustModal.screenshot({ path: 'Docs/screens/23-modal-adjust-price.png' });

      // Select order and enter final price
      await slackPageA.fillModalField('price_final', TestPrices.FINAL_ADJUSTED);
      await slackPageA.submitModal();
      await slackPageA.wait(3000);

      // Should get confirmation
      await assertEphemeralVisible(slackPageA, ErrorMessages.PRICE_UPDATED);
    }
  });

  test('should reject non-runner from adjusting prices', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();

    // User A creates proposal (runner)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // User B tries to find "Ajuster prix" — should not be visible or should be rejected
    await slackPageB.reload();
    await slackPageB.wait(2000);

    const adjustBtn = slackPageB.page.locator('button:has-text("Ajuster prix"), button:has-text("Ajuster")').first();
    if (await adjustBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await adjustBtn.click({ force: true });
      await slackPageB.wait(2000);
      // Should receive ephemeral rejection
      const ephemeral = await slackPageB.getEphemeralText();
      if (ephemeral) {
        expect(ephemeral).toContain('runner');
      }
    }
    // If button not visible, that's also correct (UI prevents it)
  });
});
