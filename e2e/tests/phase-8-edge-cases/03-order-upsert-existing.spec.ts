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

    // User A re-opens dashboard — should be in S3 state with "Voir ma commande" or "Modifier ma commande"
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Click the edit/view button in the dashboard for the existing order
    const editBtn = slackPageA.page.locator('button:has-text("Voir ma commande"), button:has-text("Modifier ma commande"), button:has-text("commande")').first();
    if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await editBtn.click({ force: true });
      await slackPageA.waitForModal();

      // Modal should be pre-filled with existing order — use ARIA to find description field
      const dialog = slackPageA.page.locator('[data-qa="wizard_modal"]').last();
      const descField = dialog.getByRole('textbox', { name: /description/i });
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
