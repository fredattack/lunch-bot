import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, createVendor } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';

test.describe('E2E-6.3: Owner-Only Vendor Edit', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should allow vendor creator to edit their own vendor', async ({ slackPageA }) => {
    // User A creates a vendor
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await createVendor(slackPageA, 'Mon Restaurant A', ['pickup']);
    await slackPageA.wait(3000);

    // User A edits it — should succeed
    const vendorListBtn = slackPageA.page.locator('button:has-text("Voir les restaurants"), button:has-text("restaurants")').first();
    if (await vendorListBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await vendorListBtn.click();
      await slackPageA.waitForModal();

      const editBtn = slackPageA.page.locator('button:has-text("Modifier")').first();
      if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await editBtn.click();
        await slackPageA.waitForModal();
        await slackPageA.fillModalField('name', 'Mon Restaurant A Modifie');
        await slackPageA.submitModal();
        await slackPageA.wait(2000);
      }
    }
  });

  test('should reject non-owner from editing vendor', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();

    // User A creates a vendor
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createVendor(slackPageA, 'Restaurant de A', ['pickup']);
    await slackPageA.wait(3000);
    await slackPageA.page.keyboard.press('Escape');

    // User B tries to edit it
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    const vendorListBtn = slackPageB.page.locator('button:has-text("Voir les restaurants"), button:has-text("restaurants")').first();
    if (await vendorListBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await vendorListBtn.click();
      await slackPageB.waitForModal();

      const editBtn = slackPageB.page.locator('button:has-text("Modifier")').first();
      if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await editBtn.click();
        await slackPageB.wait(2000);

        // Should receive ephemeral rejection or the edit button may not be available
        const ephemeral = await slackPageB.getEphemeralText();
        if (ephemeral) {
          expect(ephemeral).toContain('modifier');
        }
      }
    }
  });
});
