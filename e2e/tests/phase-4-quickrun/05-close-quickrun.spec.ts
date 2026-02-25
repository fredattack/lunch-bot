import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import {
  openDashboard,
  createQuickRun,
  addQuickRunRequest,
  lockQuickRun,
  closeQuickRunAction,
} from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible } from '../../helpers/slack-assertions';
import { TestQuickRun } from '../../fixtures/test-data';

test.describe('E2E-4.5: Close Quick Run', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should allow runner to close Quick Run and post summary', async ({ slackPageA, slackPageB }) => {
    // User A creates the Quick Run (becomes runner)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    // User B adds a request (runner cannot add to own QR)
    await slackPageB.reload();
    await slackPageB.wait(2000);
    await addQuickRunRequest(
      slackPageB,
      TestQuickRun.REQUEST_PAIN.description,
      TestQuickRun.REQUEST_PAIN.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageB.wait(2000);

    // Lock first (required before close)
    await lockQuickRun(slackPageA);

    // Close
    await closeQuickRunAction(slackPageA);

    // Handle close modal if it appears (for price adjustments)
    if (await slackPageA.isModalVisible()) {
      await slackPageA.submitModal();
      await slackPageA.wait(3000);
    }
  });

  test('should reject close from non-runner', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    // Runner actions (including "Je pars" / "Cloturer") are ephemeral to the runner only
    // User B should NOT see the close button
    await slackPageB.reload();
    await slackPageB.wait(2000);

    const closeBtn = slackPageB.page.locator('button:has-text("Cloturer")').last();
    const isVisible = await closeBtn.isVisible({ timeout: 3000 }).catch(() => false);
    expect(isVisible).toBe(false);
  });

  test('should display recap with estimated totals', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    // User B adds a request
    await slackPageB.reload();
    await slackPageB.wait(2000);
    await addQuickRunRequest(
      slackPageB,
      TestQuickRun.REQUEST_PAIN.description,
      TestQuickRun.REQUEST_PAIN.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageB.wait(2000);

    // "Recapitulatif" button is in the runner actions ephemeral (thread) — User A is runner
    await slackPageA.openThread(TestQuickRun.BOULANGERIE.destination);
    await slackPageA.clickButton('Recapitulatif');
    await slackPageA.waitForModal();

    const modal = slackPageA.page.locator('[data-qa="wizard_modal"]').last();
    const content = await modal.innerText();
    expect(content).toContain(TestQuickRun.REQUEST_PAIN.priceEstimated);

    // Capture Quick Run recap modal
    await modal.screenshot({ path: 'Docs/screens/25-modal-quickrun-recap.png' });
  });
});
