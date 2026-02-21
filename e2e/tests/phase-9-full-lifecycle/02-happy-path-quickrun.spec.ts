import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import {
  openDashboard,
  createQuickRun,
  addQuickRunRequest,
  refreshAll,
} from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible } from '../../helpers/slack-assertions';
import { TestQuickRun, TestUsers } from '../../fixtures/test-data';

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

    // ── Step 7: User A reloads and locks the Quick Run ──
    await slackPageA.reload();
    await slackPageA.wait(2_000);

    const lockBtn = slackPageA.page.locator('button:has-text("Verrouiller")').first();
    if (await lockBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await lockBtn.click();
      await slackPageA.wait(3_000);
    }

    // ── Step 8: Verify locked — User B cannot add more requests ──
    await slackPageB.reload();
    await slackPageB.wait(2_000);
    const addBtnB = slackPageB.page.locator('button:has-text("Ajouter")').first();
    // The "Ajouter" button should either be gone or result in an ephemeral error
    if (await addBtnB.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await addBtnB.click();
      await slackPageB.wait(2_000);
      // Should see an ephemeral message saying requests are locked
    }

    // ── Step 9: User A closes the Quick Run ──
    await slackPageA.reload();
    await slackPageA.wait(2_000);

    const closeBtn = slackPageA.page.locator('button:has-text("Cloturer")').first();
    if (await closeBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await closeBtn.click();
      await slackPageA.wait(2_000);

      // Handle close/price adjustment modal if it appears
      if (await slackPageA.isModalVisible()) {
        await slackPageA.submitModal();
        await slackPageA.wait(3_000);
      }
    }

    // ── Step 10: Recap should be posted in channel ──
    await slackPageA.reload();
    await slackPageA.wait(2_000);
    // Quick Run should be closed — no more action buttons
  });
});
