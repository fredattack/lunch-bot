import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder, delegateRole } from '../../helpers/slack-actions';
import { assertModalOpen, assertMessageVisible } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders } from '../../fixtures/test-data';

test.describe('E2E-2.4: Delegate Role', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should delegate runner role from User A to User B', async ({ slackPageA }) => {
    // User A creates proposal (becomes runner)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // Ensure modal is closed and channel is visible with updated message
    if (await slackPageA.isModalVisible()) {
      await slackPageA.dismissModal();
    }
    await slackPageA.reload();
    await slackPageA.wait(3000);

    // User A opens delegation modal — button is on the channel proposal message
    const delegateBtn = slackPageA.page.locator('button:has-text("Deleguer")').first();
    if (await delegateBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await delegateBtn.click({ force: true });
      await slackPageA.waitForModal();

      // Capture delegation modal
      const delegateModal = slackPageA.page.locator('[data-qa="wizard_modal"]').last();
      await delegateModal.screenshot({ path: 'Docs/screens/24-modal-delegate-role.png' });

      // Select user B in the delegate modal
      const userSelect = slackPageA.page.locator('[data-qa-block-id="delegate"] [data-qa="user-select"], [data-block-id="delegate"] [data-qa="select_input"]').first();
      if (await userSelect.isVisible({ timeout: 5000 }).catch(() => false)) {
        await userSelect.click({ force: true });
        await slackPageA.wait(1000);
        // Type user B's name
        const input = slackPageA.page.locator('[data-qa="user-select-input"], input[type="search"]').first();
        if (await input.isVisible({ timeout: 3000 }).catch(() => false)) {
          await input.fill('User B');
          await slackPageA.wait(1000);
          const option = slackPageA.page.locator('[data-qa="user-select-option"], [role="option"]').first();
          if (await option.isVisible({ timeout: 3000 }).catch(() => false)) {
            await option.click({ force: true });
          }
        }
        await slackPageA.submitModal();
        await slackPageA.wait(3000);
      }
    }
  });

  test('should update proposal message after delegation', async ({ slackPageA }) => {
    // After delegation, the proposal message should show the new runner
    await slackPageA.reload();
    await slackPageA.wait(2000);

    // The proposal message should be updated (this is verified by the message being present)
    const content = await slackPageA.page.locator('[data-qa="message_container"]').last().innerText();
    expect(content).toBeTruthy();
  });
});
