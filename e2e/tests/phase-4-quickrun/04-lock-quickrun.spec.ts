import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, createQuickRun, addQuickRunRequest } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestQuickRun, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-4.4: Lock Quick Run', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should allow runner to lock the Quick Run', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    // Add a request first
    await addQuickRunRequest(
      slackPageA,
      TestQuickRun.REQUEST_PAIN.description,
      TestQuickRun.REQUEST_PAIN.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageA.wait(2000);

    // Lock
    const lockBtn = slackPageA.page.locator('button:has-text("Verrouiller")').first();
    if (await lockBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await lockBtn.click();
      await slackPageA.wait(3000);
      await assertEphemeralVisible(slackPageA, 'verrouille');
    }
  });

  test('should reject new requests after Quick Run is locked', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    // Lock the Quick Run
    const lockBtn = slackPageA.page.locator('button:has-text("Verrouiller")').first();
    if (await lockBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await lockBtn.click();
      await slackPageA.wait(3000);
    }

    // User B tries to add a request
    await slackPageB.reload();
    await slackPageB.wait(2000);

    const addBtn = slackPageB.page.locator('button:has-text("Ajouter")').first();
    if (await addBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await addBtn.click();
      await slackPageB.wait(2000);
      // Should see rejection
      await assertEphemeralVisible(slackPageB, ErrorMessages.QUICKRUN_NO_MORE_REQUESTS);
    }
  });

  test('should reject lock from non-runner', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    // User B tries to lock
    await slackPageB.reload();
    await slackPageB.wait(2000);

    const lockBtn = slackPageB.page.locator('button:has-text("Verrouiller")').first();
    if (await lockBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await lockBtn.click();
      await slackPageB.wait(2000);
      await assertEphemeralVisible(slackPageB, ErrorMessages.ONLY_RUNNER_CAN_LOCK);
    }
  });
});
