import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, createVendor } from '../../helpers/slack-actions';
import { assertModalOpen, assertModalError } from '../../helpers/slack-assertions';
import { ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-5.1: Create Vendor', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should create a new vendor with name and fulfillment types', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await createVendor(slackPageA, 'Nouveau Restaurant E2E', ['pickup', 'delivery']);
    await slackPageA.wait(3000);
  });

  test('should create vendor with minimal fields (name + one type)', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await createVendor(slackPageA, 'Resto Minimal', ['pickup']);
    await slackPageA.wait(2000);
  });

  test('should reject vendor creation without name', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    // Dismiss the dashboard modal to access the kickoff message button
    await slackPageA.dismissModal();
    await slackPageA.clickButton('Ajouter une enseigne');
    await slackPageA.waitForModal();

    await slackPageA.checkModalCheckbox('fulfillment_types', 'pickup');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.VENDOR_NAME_REQUIRED);
  });
});
