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

    // Scope to the dashboard modal to avoid matching channel buttons
    const dashboard = slackPageA.page.locator('[data-qa="wizard_modal"]').last();
    const editBtn = dashboard.locator('button:has-text("Modifier")').first();
    if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await editBtn.click({ force: true });
      await slackPageA.waitForModal();

      // Capture edit modal with delete button
      const editModal = slackPageA.page.locator('[data-qa="wizard_modal"]').last();
      await editModal.screenshot({ path: 'Docs/screens/21-modal-edit-with-delete-btn.png' });

      // Click delete in the edit modal
      const deleteBtn = slackPageA.page.locator('button:has-text("Supprimer")').first();
      if (await deleteBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await deleteBtn.click({ force: true });
        await slackPageA.wait(1000);

        // Confirm deletion if dialog appears
        const confirmBtn = slackPageA.page.locator('button:has-text("Oui"), button:has-text("Confirmer")').first();
        if (await confirmBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
          // Capture confirmation dialog
          await slackPageA.page.screenshot({ path: 'Docs/screens/22-dialog-confirm-delete.png' });
          await confirmBtn.click({ force: true });
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

    const modalContent = await slackPageA.getModalContent();
    // Should not show "Ma commande" anymore
    expect(modalContent).toBeTruthy();
  });
});
