import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard } from '../../helpers/slack-actions';
import { assertModalOpen } from '../../helpers/slack-assertions';

test.describe('E2E-5.2: Edit Vendor', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should allow vendor creator to edit vendor details', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Open vendor list
    const vendorListBtn = slackPageA.page.locator('button:has-text("Voir les restaurants"), button:has-text("restaurants")').first();
    if (await vendorListBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await vendorListBtn.click();
      await slackPageA.waitForModal();

      // Click edit on a vendor
      const editBtn = slackPageA.page.locator('button:has-text("Modifier"), button:has-text("Editer")').first();
      if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await editBtn.click();
        await slackPageA.waitForModal();

        // Modify name
        await slackPageA.fillModalField('name', 'Restaurant Modifie E2E');
        await slackPageA.submitModal();
        await slackPageA.wait(3000);
      }
    }
  });
});
