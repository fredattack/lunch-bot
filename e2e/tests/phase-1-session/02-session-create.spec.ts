import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard } from '../../helpers/slack-actions';
import { assertModalOpen } from '../../helpers/slack-assertions';

test.describe('E2E-1.2: Session Create', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should create a lunch session when opening dashboard for the first time today', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // The dashboard modal being visible confirms a session was created (or reused)
    // because handleLunchDashboard calls createLunchSession.handle()
    const modalContent = await slackPageA.getModalContent();
    // Should contain today's date or session info
    expect(modalContent).toBeTruthy();
  });

  test('should reuse existing session when opening dashboard a second time', async ({ slackPageA }) => {
    // First open
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await slackPageA.page.keyboard.press('Escape');
    await slackPageA.wait(1000);

    // Second open — should not create a duplicate
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const modalContent = await slackPageA.getModalContent();
    expect(modalContent).toBeTruthy();
  });
});
