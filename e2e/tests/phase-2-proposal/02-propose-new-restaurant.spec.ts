import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeNewRestaurant, placeOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertModalError } from '../../helpers/slack-assertions';
import { ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-2.2: Propose New Restaurant', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should create a new vendor and proposal when proposing new restaurant', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await proposeNewRestaurant(slackPageA, 'Nouveau Resto E2E', ['pickup']);

    // Modal should transition to order form
    await assertModalOpen(slackPageA);

    // Place an order to complete the flow
    await placeOrder(slackPageA, 'Plat du jour', '10');
    await slackPageA.wait(2000);
  });

  test('should reject proposal with empty restaurant name', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await slackPageA.clickButton('Proposer un nouveau restaurant');
    await slackPageA.waitForModal();

    // Don't fill name, just check a fulfillment type and submit
    await slackPageA.checkModalCheckbox('fulfillment_types', 'pickup');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.VENDOR_NAME_REQUIRED);
  });

  test('should reject proposal without fulfillment types selected', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await slackPageA.clickButton('Proposer un nouveau restaurant');
    await slackPageA.waitForModal();

    // Fill name but don't select any fulfillment type
    await slackPageA.fillModalField('name', 'Resto Sans Type');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.FULFILLMENT_REQUIRED);
  });
});
