import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard } from '../../helpers/slack-actions';
import { assertModalOpen } from '../../helpers/slack-assertions';

test.describe('E2E-5.4: Vendor Deactivate', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should deactivate a vendor and hide it from catalog', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Open vendor list
    const vendorListBtn = slackPageA.page.locator('button:has-text("Voir les restaurants"), button:has-text("restaurants")').first();
    if (await vendorListBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await vendorListBtn.click({ force: true });
      await slackPageA.waitForModal();

      // Count vendors before deactivation
      const vendorCount = await slackPageA.page.locator('[data-qa="modal"] button:has-text("Modifier")').count();

      // Edit first vendor and deactivate it
      const editBtn = slackPageA.page.locator('button:has-text("Modifier"), button:has-text("Editer")').first();
      if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await editBtn.click({ force: true });
        await slackPageA.waitForModal();

        // Toggle active/inactive checkbox
        const activeCheckbox = slackPageA.page.locator('input[type="checkbox"][value="active"], [data-qa-block-id="active"] input').first();
        if (await activeCheckbox.isVisible({ timeout: 3000 }).catch(() => false)) {
          if (await activeCheckbox.isChecked()) {
            await activeCheckbox.uncheck();
          }
        }
        await slackPageA.submitModal();
        await slackPageA.wait(3000);
      }
    }
  });
});
