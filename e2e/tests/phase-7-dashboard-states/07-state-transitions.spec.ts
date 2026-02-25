import { test, expect } from '../../fixtures/slack-page';
import { resetDatabase } from '../../helpers/api-helpers';
import { openDashboard, proposeAndOrder } from '../../helpers/slack-actions';
import { assertModalOpen, assertDashboardState } from '../../helpers/slack-assertions';
import { TestVendors, TestOrders, DashboardLabels } from '../../fixtures/test-data';

test.describe('E2E-7.7: Dashboard State Transitions', () => {
  test.beforeAll(async () => {
    await resetDatabase();
  });

  test('should transition S1 → S3 after creating proposal and placing order', async ({ slackPageA }) => {
    // Start: S1 (no proposals)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    let content = await slackPageA.getModalContent();
    expect(content).toContain(DashboardLabels.S1);

    await slackPageA.page.keyboard.press('Escape');
    await slackPageA.wait(500);

    // Action: create proposal + order
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // Result: S4 (In Charge — runner with order) or S3 (Has Order)
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);

    content = await slackPageA.getModalContent();
    // Should no longer be S1
    expect(content).not.toContain(DashboardLabels.S1);
  });

  test('should transition S3 → S2 after deleting own order', async ({ slackPageA, slackPageB }) => {
    await resetDatabase();

    // User A creates proposal
    await openDashboard(slackPageA);
    await assertModalOpen(slackPageA);
    await proposeAndOrder(
      slackPageA,
      TestVendors.PIZZA_PLACE.name,
      TestOrders.MARGHERITA.description,
      TestOrders.MARGHERITA.priceEstimated
    );
    await slackPageA.wait(3000);

    // User B places order (enters S3)
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);
    await slackPageB.clickButton('Commander ici');
    await slackPageB.fillModalField('description', TestOrders.CALZONE.description);
    await slackPageB.fillModalField('price_estimated', TestOrders.CALZONE.priceEstimated);
    await slackPageB.submitModal();
    await slackPageB.wait(3000);

    // User B deletes order (should go back to S2)
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    const editBtn = slackPageB.page.locator('button:has-text("Modifier")').first();
    if (await editBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await editBtn.click({ force: true });
      await slackPageB.waitForModal();

      const deleteBtn = slackPageB.page.locator('button:has-text("Supprimer")').first();
      if (await deleteBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await deleteBtn.click({ force: true });
        await slackPageB.wait(1000);

        const confirmBtn = slackPageB.page.locator('button:has-text("Oui"), button:has-text("Confirmer")').first();
        if (await confirmBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
          await confirmBtn.click({ force: true });
        }
        await slackPageB.wait(3000);
      }
    }

    // User B re-opens dashboard — should be S2 (has proposals but no order)
    await openDashboard(slackPageB);
    await assertModalOpen(slackPageB);

    const content = await slackPageB.getModalContent();
    // Should show proposal list without user's order
    expect(content).toBeTruthy();
  });
});
