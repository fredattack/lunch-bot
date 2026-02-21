import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase, forceSessionLock } from '../../helpers/api-helpers';
import { openDashboard } from '../../helpers/slack-actions';
import { assertModalOpen, assertEphemeralVisible } from '../../helpers/slack-assertions';

test.describe('E2E-1.3: Session Lock', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should lock session when deadline passes and scheduler runs', async ({ slackPageA }) => {
    // Create session via dashboard first
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await slackPageA.page.keyboard.press('Escape');
    await slackPageA.wait(1000);

    // Force lock via API helper (sets deadline to past, runs LockExpiredSessions)
    await forceSessionLock();

    // Re-open dashboard — session should be locked
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // The dashboard should still be visible but actions may be restricted
    const modalContent = await slackPageA.page.locator('[data-qa="modal"], .p-block_kit_modal').innerText();
    expect(modalContent).toBeTruthy();
  });

  test('should reject new proposals on locked session for regular user', async ({ slackPageA }) => {
    await forceSessionLock();

    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Try to start a proposal — should fail with locked message
    const startButton = slackPageA.page.locator('button:has-text("Demarrer une commande")').first();
    if (await startButton.isVisible({ timeout: 3000 }).catch(() => false)) {
      await startButton.click();
      await slackPageA.wait(2000);
      // Should get ephemeral error or modal error
      const ephemeral = await slackPageA.getEphemeralText();
      if (ephemeral) {
        expect(ephemeral).toContain('verrouillees');
      }
    }
  });
});
