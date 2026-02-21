import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeFromCatalog, createQuickRun } from '../../helpers/slack-actions';
import { assertModalOpen, assertModalError } from '../../helpers/slack-assertions';
import { TestVendors, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-8.5: Validation Errors', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should show error for empty order description', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await assertModalOpen(slackPageA);

    // Submit without description
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.DESCRIPTION_REQUIRED);
  });

  test('should show error for invalid price format', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await assertModalOpen(slackPageA);

    await slackPageA.fillModalField('description', 'Test plat');
    await slackPageA.fillModalField('price_estimated', 'abc');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.PRICE_ESTIMATED_INVALID);
  });

  test('should show error for Quick Run with empty destination', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await slackPageA.clickButton('Quick Run');
    await slackPageA.waitForModal();

    await slackPageA.fillModalField('delay', '30');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.DESTINATION_REQUIRED);
  });

  test('should show error for Quick Run with delay out of range', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await slackPageA.clickButton('Quick Run');
    await slackPageA.waitForModal();

    await slackPageA.fillModalField('destination', 'Test');
    await slackPageA.fillModalField('delay', '200');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.DELAY_INVALID);
  });
});
