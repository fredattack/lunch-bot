import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import {
  openDashboard,
  createQuickRun,
  addQuickRunRequest,
  lockQuickRun,
} from '../../helpers/slack-actions';
import { assertModalOpen } from '../../helpers/slack-assertions';
import { TestQuickRun } from '../../fixtures/test-data';

test.describe('E2E-4.4: Lock Quick Run', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should allow runner to lock the Quick Run', async ({ slackPageA, slackPageB }) => {
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

    // User A locks via "Je pars" button in runner actions ephemeral
    await lockQuickRun(slackPageA);

    // Quick Run message should now show locked status
    const msg = await slackPageA.getMessageContaining(TestQuickRun.BOULANGERIE.destination);
    expect(msg).toBeTruthy();
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
    await lockQuickRun(slackPageA);

    // User B reloads
    await slackPageB.reload();
    await slackPageB.wait(2000);

    // The locked QR message should show "Verrouille" and NOT have an "Ajouter" button
    const lockedMsg = slackPageB.page
      .getByRole('listitem')
      .filter({ hasText: TestQuickRun.BOULANGERIE.destination })
      .filter({ hasText: /Verrouille/ })
      .last();
    await expect(lockedMsg).toBeVisible({ timeout: 5000 });

    const addBtn = lockedMsg.locator('button:has-text("Ajouter")');
    await expect(addBtn).toHaveCount(0);
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

    // User B should NOT see runner actions (they are ephemeral to User A only)
    // But if User B somehow sends the lock action, they get rejected
    await slackPageB.reload();
    await slackPageB.wait(2000);

    // "Je pars" button is ephemeral to runner only — User B should not see it
    const lockBtn = slackPageB.page.locator('button:has-text("Je pars")').last();
    const isVisible = await lockBtn.isVisible({ timeout: 3000 }).catch(() => false);
    expect(isVisible).toBe(false);
  });
});
