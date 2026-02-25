import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase, deactivateAllVendors } from '../../helpers/api-helpers';
import { openDashboard } from '../../helpers/slack-actions';
import { assertModalOpen } from '../../helpers/slack-assertions';

test.describe('E2E-8.4: Empty Catalog Fallback', () => {
  test.beforeAll(async () => {
    await resetDatabase();
    await deactivateAllVendors();
  });

  test('should fallback to "propose new restaurant" when catalog is empty', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Click "Demarrer une commande" — with no vendors, should show restaurant creation modal
    await slackPageA.clickButton('Demarrer une commande');
    await slackPageA.waitForModal();

    // The modal should be the "propose restaurant" form (not the vendor select)
    // Verify by checking for the name field using ARIA label
    const dialog = slackPageA.page.locator('[data-qa="wizard_modal"]').last();
    const nameField = dialog.getByRole('textbox', { name: /nom/i });
    const isNameFieldVisible = await nameField.isVisible({ timeout: 5000 }).catch(() => false);

    expect(isNameFieldVisible).toBe(true);
  });
});
