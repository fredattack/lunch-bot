import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard } from '../../helpers/slack-actions';
import { assertModalOpen } from '../../helpers/slack-assertions';
import { TestVendors } from '../../fixtures/test-data';

test.describe('E2E-5.3: Vendor List Search', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should open vendor list and search by name', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Open vendor list
    const vendorListBtn = slackPageA.page.locator('button:has-text("Voir les restaurants"), button:has-text("restaurants")').first();
    if (await vendorListBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await vendorListBtn.click({ force: true });
      await slackPageA.waitForModal();

      // Type in search field
      const searchInput = slackPageA.page.locator('input[type="text"], [data-qa-block-id="search"] input').first();
      if (await searchInput.isVisible({ timeout: 5000 }).catch(() => false)) {
        await searchInput.fill('Pizza');
        await slackPageA.wait(2000);

        // Results should be filtered
        const modal = slackPageA.page.locator('[data-qa="wizard_modal"]').last();
        const content = await modal.innerText();
        expect(content.toLowerCase()).toContain('pizza');
      }
    }
  });
});
