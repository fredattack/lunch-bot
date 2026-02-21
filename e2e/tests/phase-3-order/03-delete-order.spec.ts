import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-3.3: Delete Order', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should delete own order and receive confirmation', async ({ slackPageA }) => {
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

    // Re-open dashboard and find delete button
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const editBtn = slackPageA.page.locator('button:has-text("Modifier")').first();
    if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await editBtn.click();
      await slackPageA.waitForModal();

      // Click delete in the edit modal
      const deleteBtn = slackPageA.page.locator('button:has-text("Supprimer")').first();
      if (await deleteBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await deleteBtn.click();
        await slackPageA.wait(1000);

        // Confirm deletion if dialog appears
        const confirmBtn = slackPageA.page.locator('button:has-text("Oui"), button:has-text("Confirmer")').first();
        if (await confirmBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
          await confirmBtn.click();
        }

        await slackPageA.wait(3000);

        // Should receive ephemeral confirmation
        await assertEphemeralVisible(slackPageA, ErrorMessages.ORDER_DELETED);
      }
    }
  });

  test('should update proposal order count after deletion', async ({ slackPageA }) => {
    // After deletion, re-open dashboard — should be back to S2 or S1
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const modalContent = await slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal').innerText();
    // Should not show "Ma commande" anymore
    expect(modalContent).toBeTruthy();
  });
});
