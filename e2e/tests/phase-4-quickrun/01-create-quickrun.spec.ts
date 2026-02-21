import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, createQuickRun } from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible, assertModalError } from '../../helpers/slack-assertions';
import { TestQuickRun, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-4.1: Create Quick Run', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should create a Quick Run with destination and delay', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await createQuickRun(
      slackPageA,
      TestQuickRun.BOULANGERIE.destination,
      TestQuickRun.BOULANGERIE.delayMinutes
    );

    await slackPageA.wait(3000);

    // Quick Run message should be posted in the channel
    await assertMessageVisible(slackPageA, TestQuickRun.BOULANGERIE.destination);
  });

  test('should reject Quick Run with empty destination', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await slackPageA.clickButton('Quick Run');
    await slackPageA.waitForModal();

    // Fill delay but not destination
    await slackPageA.fillModalField('delay', '30');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.DESTINATION_REQUIRED);
  });

  test('should reject Quick Run with invalid delay (0)', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await slackPageA.clickButton('Quick Run');
    await slackPageA.waitForModal();

    await slackPageA.fillModalField('destination', 'Test');
    await slackPageA.fillModalField('delay', '0');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.DELAY_INVALID);
  });

  test('should reject Quick Run with delay over 120 minutes', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await slackPageA.clickButton('Quick Run');
    await slackPageA.waitForModal();

    await slackPageA.fillModalField('destination', 'Test');
    await slackPageA.fillModalField('delay', '150');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.DELAY_INVALID);
  });
});
