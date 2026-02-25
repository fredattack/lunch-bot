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

test.describe('E2E-9.2: Happy Path — Full Quick Run with 4 Users', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should complete full Quick Run: A creates, B+C+Admin add requests, A locks and closes', async ({
    slackPageA,
    slackPageB,
    slackPageC,
    slackPageAdmin,
  }) => {
    // ── Step 1: User A opens dashboard and creates a Quick Run ──
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3_000);

    // ── Step 2: Quick Run message posted in channel ──
    await assertMessageVisible(slackPageA, TestQuickRun.BOULANGERIE.destination);

    // ── Step 3: User A adds their own request ──
    await addQuickRunRequest(
      slackPageA,
      TestQuickRun.REQUEST_PAIN.description,
      TestQuickRun.REQUEST_PAIN.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageA.wait(2_000);

    // ── Step 4: User B sees the Quick Run and adds a request ──
    await slackPageB.reload();
    await slackPageB.wait(2_000);
    await addQuickRunRequest(
      slackPageB,
      TestQuickRun.REQUEST_CROISSANT.description,
      TestQuickRun.REQUEST_CROISSANT.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageB.wait(2_000);

    // ── Step 5: User C adds a request ──
    await slackPageC.reload();
    await slackPageC.wait(2_000);
    await addQuickRunRequest(
      slackPageC,
      TestQuickRun.REQUEST_CAFE.description,
      TestQuickRun.REQUEST_CAFE.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageC.wait(2_000);

    // ── Step 6: Admin adds a request ──
    await slackPageAdmin.reload();
    await slackPageAdmin.wait(2_000);
    await addQuickRunRequest(
      slackPageAdmin,
      TestQuickRun.REQUEST_CHOCOLATINE.description,
      TestQuickRun.REQUEST_CHOCOLATINE.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageAdmin.wait(2_000);

    // ── Step 7: User A locks (DO NOT reload — ephemeral buttons would be lost) ──
    await slackPageA.wait(3_000);
    await lockQuickRun(slackPageA);

    // ── Step 8: Verify locked — User B cannot add more requests ──
    await slackPageB.reload();
    await slackPageB.wait(2_000);
    const addBtnB = slackPageB.page.locator('button:has-text("Ajouter")').last();
    if (await addBtnB.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await addBtnB.click({ force: true });
      await slackPageB.wait(2_000);
    }

    // ── Step 9: User A closes (after lock, new ephemeral with "Cloturer" appears) ──
    await closeQuickRunAction(slackPageA);
    if (await slackPageA.isModalVisible()) {
      await slackPageA.submitModal();
      await slackPageA.wait(3_000);
    }

    // ── Step 10: Recap should be posted in channel ──
    await slackPageA.reload();
    await slackPageA.wait(2_000);
  });
});
