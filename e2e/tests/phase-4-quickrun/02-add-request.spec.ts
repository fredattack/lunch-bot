import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, createQuickRun, addQuickRunRequest } from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible, assertModalError } from '../../helpers/slack-assertions';
import { TestQuickRun, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-4.2: Add Quick Run Request', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should add a request to an existing Quick Run', async ({ slackPageA, slackPageB }) => {
    // User A creates Quick Run
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
  });

  test('should reject request with empty description', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );
    await slackPageA.wait(3000);

    // Try to add request with empty description
    await slackPageA.clickButton('Ajouter', TestQuickRun.BOULANGERIE.destination);
    await slackPageA.waitForModal();

    await slackPageA.fillModalField('price_estimated', '5');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.DESCRIPTION_REQUIRED);
  });
});
