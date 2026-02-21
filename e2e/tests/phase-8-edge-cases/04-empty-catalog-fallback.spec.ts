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
    const modal = slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal');
    const content = await modal.innerText();

    // Should contain "name" field (new restaurant form) instead of vendor select
    const nameField = slackPageA.page.locator('[data-qa-block-id="name"] input, [data-block-id="name"] input').first();
    const isNameFieldVisible = await nameField.isVisible({ timeout: 5000 }).catch(() => false);

    expect(isNameFieldVisible).toBe(true);
  });
});
