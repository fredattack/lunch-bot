import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard } from '../../helpers/slack-actions';
import { assertModalOpen, assertButtonVisible } from '../../helpers/slack-assertions';
import { DashboardLabels } from '../../fixtures/test-data';

test.describe('E2E-1.1: Dashboard Open — State S1', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should open dashboard via /lunch command and show S1 state', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
  });

  test('should display "Aucune commande" label when no proposals exist', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    const modalContent = await slackPageA.getModalContent();
    expect(modalContent).toContain(DashboardLabels.S1);
  });

  test('should show "Demarrer une commande" CTA button', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await assertButtonVisible(slackPageA, 'Demarrer une commande');
  });

  test('should show "Proposer un nouveau restaurant" button', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await assertButtonVisible(slackPageA, 'Proposer un nouveau restaurant');
  });
});
