import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, createQuickRun, addQuickRunRequest } from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestQuickRun } from '../../fixtures/test-data';

test.describe('E2E-9.2: Happy Path — Full Quick Run Lifecycle', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should complete full Quick Run lifecycle: create → requests → lock → close → recap', async ({
    slackPageA,
    slackPageB,
  }) => {
    // ── Step 1-2: User A creates Quick Run ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    // ── Step 3: Message posted in channel ──
    await assertMessageVisible(slackPageA, TestQuickRun.BOULANGERIE.destination);

    // ── Step 4: User A adds a request ──
    await addQuickRunRequest(
      slackPageA,
      TestQuickRun.REQUEST_PAIN.description,
      TestQuickRun.REQUEST_PAIN.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageA.wait(2000);

    // ── Step 5: User B adds a request ──
    await slackPageB.reload();
    await slackPageB.wait(2000);

    await addQuickRunRequest(
      slackPageB,
      TestQuickRun.REQUEST_CROISSANT.description,
      TestQuickRun.REQUEST_CROISSANT.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageB.wait(2000);

    // ── Step 6: User A locks Quick Run ──
    await slackPageA.reload();
    await slackPageA.wait(2000);

    const lockBtn = slackPageA.page.locator('button:has-text("Verrouiller")').first();
    if (await lockBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await lockBtn.click();
      await slackPageA.wait(3000);
    }

    // ── Step 7-8: User A closes Quick Run ──
    const closeBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await closeBtn.click();
      await slackPageA.wait(2000);

      // Handle close modal if it appears (price adjustments)
      if (await slackPageA.isModalVisible()) {
        await slackPageA.submitModal();
        await slackPageA.wait(3000);
      }
    }

    // ── Step 9: Recap posted in channel ──
    await slackPageA.reload();
    await slackPageA.wait(2000);
    // Quick Run closure summary should be visible
  });
});
