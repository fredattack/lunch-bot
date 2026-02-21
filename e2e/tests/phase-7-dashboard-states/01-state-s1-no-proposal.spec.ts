import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard } from '../../helpers/slack-actions';
import { assertModalOpen, assertButtonVisible, assertDashboardState } from '../../helpers/slack-assertions';
import { DashboardLabels } from '../../fixtures/test-data';

test.describe('E2E-7.1: Dashboard State S1 — No Proposal', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should display S1 state with empty state message', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await assertDashboardState(slackPageA, DashboardLabels.S1);
  });

  test('should show "Demarrer une commande" button in S1', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await assertButtonVisible(slackPageA, 'Demarrer une commande');
  });

  test('should show "Proposer un nouveau restaurant" button in S1', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await assertButtonVisible(slackPageA, 'Proposer un nouveau restaurant');
  });
});
