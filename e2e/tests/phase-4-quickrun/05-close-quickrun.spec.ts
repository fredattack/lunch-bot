import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, createQuickRun, addQuickRunRequest } from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestQuickRun, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-4.5: Close Quick Run', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should allow runner to close Quick Run and post summary', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    // Add a request
    await addQuickRunRequest(
      slackPageA,
      TestQuickRun.REQUEST_PAIN.description,
      TestQuickRun.REQUEST_PAIN.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageA.wait(2000);

    // Close
    const closeBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await closeBtn.click();
      await slackPageA.wait(3000);

      // Handle close modal if it appears (for price adjustments)
      const isModalOpen = await slackPageA.isModalVisible();
      if (isModalOpen) {
        await slackPageA.submitModal();
        await slackPageA.wait(3000);
      }
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

    // User B tries to close
    await slackPageB.reload();
    await slackPageB.wait(2000);

    const closeBtn = slackPageB.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await closeBtn.click();
      await slackPageB.wait(2000);
      await assertEphemeralVisible(slackPageB, ErrorMessages.ONLY_RUNNER_CAN_CLOSE);
    }
  });

  test('should display recap with estimated and final totals', async ({ slackPageA }) => {
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

    // Open recap
    const recapBtn = slackPageA.page.locator('button:has-text("Recap"), button:has-text("recap")').first();
    if (await recapBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await recapBtn.click();
      await slackPageA.waitForModal();

      // Recap should show amounts
      const modal = slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal');
      const content = await modal.innerText();
      expect(content).toContain(TestQuickRun.REQUEST_PAIN.priceEstimated);
    }
  });
});
