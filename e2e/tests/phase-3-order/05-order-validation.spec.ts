import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeFromCatalog, placeOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertModalError } from '../../helpers/slack-assertions';
import { TestVendors, TestPrices, ErrorMessages } from '../../fixtures/test-data';

test.describe('E2E-3.5: Order Validation', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should reject order with empty description', async ({ slackPageA }) => {
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await assertModalOpen(slackPageA);

    // Submit with empty description
    await slackPageA.fillModalField('price_estimated', '10');
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.DESCRIPTION_REQUIRED);
  });

  test('should reject order with invalid price (letters)', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await assertModalOpen(slackPageA);

    await slackPageA.fillModalField('description', 'Test plat');
    await slackPageA.fillModalField('price_estimated', TestPrices.INVALID_LETTERS);
    await slackPageA.submitModal();
    await slackPageA.wait(2000);

    await assertModalError(slackPageA, ErrorMessages.PRICE_ESTIMATED_INVALID);
  });

  test('should accept order with comma-separated price', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await assertModalOpen(slackPageA);

    // Price with comma should be accepted (converted to dot)
    await placeOrder(slackPageA, 'Plat comma test', TestPrices.VALID_COMMA);
    await slackPageA.wait(2000);

    // No error — modal should close
    const isModalVisible = await slackPageA.isModalVisible();
    expect(isModalVisible).toBe(false);
  });

  test('should accept order without price (optional)', async ({ slackPageA }) => {
    await resetDatabase();
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    await proposeFromCatalog(slackPageA, TestVendors.PIZZA_PLACE.name);
    await assertModalOpen(slackPageA);

    // Only description, no price
    await placeOrder(slackPageA, 'Plat sans prix');
    await slackPageA.wait(2000);

    const isModalVisible = await slackPageA.isModalVisible();
    expect(isModalVisible).toBe(false);
  });
});
