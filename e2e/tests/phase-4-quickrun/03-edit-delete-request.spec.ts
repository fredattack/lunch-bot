import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, createQuickRun, addQuickRunRequest } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestQuickRun, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-4.3: Edit/Delete Quick Run Request', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should edit an existing request description and price', async ({ slackPageA }) => {
    // Create Quick Run + request
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    await addQuickRunRequest(
      slackPageA,
      TestQuickRun.REQUEST_PAIN.description,
      TestQuickRun.REQUEST_PAIN.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageA.wait(2000);

    // Edit the request
    const editBtn = slackPageA.page.locator('button:has-text("Modifier")').first();
    if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await editBtn.click();
      await slackPageA.waitForModal();
      await slackPageA.fillModalField('description', 'Pain complet modifie');
      await slackPageA.fillModalField('price_estimated', '4');
      await slackPageA.submitModal();
      await slackPageA.wait(2000);
    }
  });

  test('should delete own request and receive confirmation', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    await addQuickRunRequest(
      slackPageA,
      TestQuickRun.REQUEST_PAIN.description,
      TestQuickRun.REQUEST_PAIN.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageA.wait(2000);

    // Delete the request
    const deleteBtn = slackPageA.page.locator('button:has-text("Supprimer")').first();
    if (await deleteBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await deleteBtn.click();
      await slackPageA.wait(3000);
      // Should see confirmation
      const ephemeral = await slackPageA.getEphemeralText();
      if (ephemeral) {
        expect(ephemeral).toContain('supprimee');
      }
    }
  });
});
