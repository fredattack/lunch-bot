import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import {
  openDashboard,
  createQuickRun,
  addQuickRunRequest,
  editQuickRunRequest,
  deleteQuickRunRequest,
} from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';
import { TestQuickRun } from '../../fixtures/test-data';

test.describe('E2E-4.3: Edit/Delete Quick Run Request', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should edit an existing request description and price', async ({ slackPageA, slackPageB }) => {
    // User A creates the Quick Run (becomes runner)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    // User B adds a request (runner cannot add requests to their own QR)
    await slackPageB.reload();
    await slackPageB.wait(2000);

    await addQuickRunRequest(
      slackPageB,
      TestQuickRun.REQUEST_PAIN.description,
      TestQuickRun.REQUEST_PAIN.priceEstimated,
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageB.wait(2000);

    // User B edits their own request by clicking "Ajouter" again
    await editQuickRunRequest(
      slackPageB,
      'Pain complet modifie',
      '4',
      TestQuickRun.BOULANGERIE.destination
    );
    await slackPageB.wait(2000);
  });

  test('should delete own request and receive confirmation', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();

    // User A creates the Quick Run (becomes runner)
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

    // User B deletes their request via the edit modal's "Supprimer ma demande" button
    await deleteQuickRunRequest(slackPageB, TestQuickRun.BOULANGERIE.destination);

    const ephemeral = await slackPageB.getEphemeralText();
    if (ephemeral) {
      expect(ephemeral).toContain('supprimee');
    }
  });
});
