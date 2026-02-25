import { SlackPage } from '../fixtures/slack-page';
import { lockLatestQuickRun, closeLatestQuickRun } from './api-helpers';

/**
 * Reusable higher-level Slack actions composed from SlackPage primitives.
 * Each function performs a complete user workflow step.
 */

// ── Dashboard ────────────────────────────────────────────────

export async function openDashboard(slack: SlackPage): Promise<void> {
  for (let attempt = 1; attempt <= 3; attempt++) {
    await slack.sendSlashCommand('/lunch');
    try {
      await slack.waitForModal();
      return;
    } catch {
      if (attempt === 3) {
        throw new Error('openDashboard: Modal did not appear after 3 attempts');
      }
      await slack.page.keyboard.press('Escape');
      await slack.wait(1500);
    }
  }
}

// ── Proposal from catalog ────────────────────────────────────

export async function proposeFromCatalog(
  slack: SlackPage,
  vendorName: string,
  fulfillment: 'pickup' | 'delivery' = 'pickup'
): Promise<void> {
  await slack.clickButton('Demarrer une commande');
  // selectModalOption will wait for the proposal modal via findFieldByLabel retry
  await slack.selectModalOption('enseigne', vendorName);
  if (fulfillment === 'delivery') {
    await slack.selectModalOption('fulfillment', 'Livraison');
  }
  await slack.submitModal();
  // Modal transitions to order form — fillModalField will wait for it
}

// ── Propose new restaurant ───────────────────────────────────

export async function proposeNewRestaurant(
  slack: SlackPage,
  name: string,
  fulfillmentTypes: string[] = ['pickup']
): Promise<void> {
  await slack.clickButton('Proposer un nouveau restaurant');
  await slack.waitForModal();
  await slack.fillModalField('name', name);
  for (const ft of fulfillmentTypes) {
    await slack.checkModalCheckbox('fulfillment_types', ft);
  }
  await slack.submitModal();
  // Modal transitions to order form
  await slack.waitForModal();
}

// ── Place order ──────────────────────────────────────────────

export async function placeOrder(
  slack: SlackPage,
  description: string,
  priceEstimated?: string,
  notes?: string
): Promise<void> {
  await slack.fillModalField('description', description);
  if (priceEstimated) {
    await slack.fillModalField('price_estimated', priceEstimated);
  }
  if (notes) {
    await slack.fillModalField('notes', notes);
  }
  await slack.submitModal();
}

// ── Edit order ───────────────────────────────────────────────

export async function editOrder(
  slack: SlackPage,
  newDescription: string,
  newPrice?: string
): Promise<void> {
  await slack.waitForModal();
  await slack.fillModalField('description', newDescription);
  if (newPrice) {
    await slack.fillModalField('price_estimated', newPrice);
  }
  await slack.submitModal();
}

// ── Full proposal + order in one step ────────────────────────

export async function proposeAndOrder(
  slack: SlackPage,
  vendorName: string,
  orderDescription: string,
  orderPrice: string,
  fulfillment: 'pickup' | 'delivery' = 'pickup'
): Promise<void> {
  await proposeFromCatalog(slack, vendorName, fulfillment);
  await placeOrder(slack, orderDescription, orderPrice);
}

// ── Join existing proposal (order on an open proposal) ───────

export async function joinProposal(
  slack: SlackPage,
  buttonText: string,
  orderDescription: string,
  orderPrice: string
): Promise<void> {
  await slack.clickButton(buttonText);
  await slack.waitForModal();
  await placeOrder(slack, orderDescription, orderPrice);
}

// ── Open dashboard and order from a visible proposal ─────────

export async function dashboardOrderHere(
  slack: SlackPage,
  orderDescription: string,
  orderPrice: string
): Promise<void> {
  await slack.clickButton('Commander ici');
  await placeOrder(slack, orderDescription, orderPrice);
}

// ── Launch another proposal from dashboard (when one exists) ─

export async function launchAnotherProposal(
  slack: SlackPage,
  vendorName: string,
  fulfillment: 'pickup' | 'delivery' = 'pickup'
): Promise<void> {
  const startBtn = slack.page
    .locator('button:has-text("Lancer une autre"), button:has-text("Demarrer"), button:has-text("Proposer")')
    .first();
  await startBtn.waitFor({ timeout: 5_000 });
  await startBtn.click({ force: true });
  await slack.waitForModal();
  await slack.selectModalOption('enseigne', vendorName);
  if (fulfillment === 'delivery') {
    await slack.selectModalOption('fulfillment', 'Livraison');
  }
  await slack.submitModal();
  await slack.waitForModal();
}

// ── Claim role ───────────────────────────────────────────────

export async function claimRole(
  slack: SlackPage,
  role: 'runner' | 'orderer',
  proposalText?: string
): Promise<void> {
  const buttonText = role === 'runner' ? 'Je suis runner' : 'Je suis orderer';
  await slack.clickButton(buttonText, proposalText);
}

// ── Take charge (prendre en charge) ──────────────────────────

export async function takeCharge(
  slack: SlackPage,
  proposalText?: string
): Promise<void> {
  await slack.clickButton('Prendre en charge', proposalText);
}

// ── Delegate role ────────────────────────────────────────────

export async function delegateRole(
  slack: SlackPage,
  targetUserName: string,
  proposalText?: string
): Promise<void> {
  await slack.clickButton('Deleguer', proposalText);
  await slack.waitForModal();
  await slack.selectUser('delegate', targetUserName);
  await slack.submitModal();
}

// ── View recap ───────────────────────────────────────────────

export async function viewRecap(slack: SlackPage, proposalText?: string): Promise<string | null> {
  const recapBtn = slack.page
    .locator('button:has-text("Recap"), button:has-text("recapitulatif")')
    .first();
  if (await recapBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
    await recapBtn.click({ force: true });
    await slack.wait(3_000);
    if (await slack.isModalVisible()) {
      return slack.getModalContent();
    }
  }
  return null;
}

// ── Adjust final price ───────────────────────────────────────

export async function adjustFinalPrice(
  slack: SlackPage,
  orderUserText: string,
  finalPrice: string,
  proposalText?: string
): Promise<void> {
  await slack.clickButton('Ajuster prix', proposalText);
  await slack.waitForModal();
  await slack.selectModalOption('order', orderUserText);
  await slack.fillModalField('price_final', finalPrice);
  await slack.submitModal();
}

// ── Close proposal ───────────────────────────────────────────

export async function closeProposal(
  slack: SlackPage,
  proposalText?: string
): Promise<void> {
  await slack.clickButton('Cloturer', proposalText);
}

// ── Close session ────────────────────────────────────────────

export async function closeSession(
  slack: SlackPage,
  sessionText?: string
): Promise<void> {
  await slack.clickButton('Cloturer la journee', sessionText);
}

// ── Quick Run ────────────────────────────────────────────────

export async function createQuickRun(
  slack: SlackPage,
  destination: string,
  delayMinutes: string,
  note?: string
): Promise<void> {
  await slack.clickButton('Quick Run');
  await slack.waitForModal();
  await slack.waitForModalContent();
  await slack.fillModalField('destination', destination);
  await slack.fillModalField('delay', delayMinutes);
  if (note) {
    await slack.fillModalField('note', note);
  }
  await slack.submitModal();
  await slack.wait(1000);
  // Dismiss any remaining modal (the dashboard modal stays open underneath)
  if (await slack.isModalVisible()) {
    await slack.dismissModal();
  }
}

export async function addQuickRunRequest(
  slack: SlackPage,
  description: string,
  priceEstimated?: string,
  quickRunText?: string
): Promise<void> {
  await slack.clickButton('Ajouter', quickRunText);
  await slack.waitForModal();
  await slack.waitForModalContent();
  await slack.fillModalField('description', description);
  if (priceEstimated) {
    await slack.fillModalField('price_estimated', priceEstimated);
  }
  await slack.submitModal();
}

export async function editQuickRunRequest(
  slack: SlackPage,
  description: string,
  priceEstimated?: string,
  quickRunText?: string
): Promise<void> {
  // Clicking "Ajouter" when user already has a request opens the edit modal
  await slack.clickButton('Ajouter', quickRunText);
  await slack.waitForModal();
  await slack.waitForModalContent();
  await slack.fillModalField('description', description);
  if (priceEstimated) {
    await slack.fillModalField('price_estimated', priceEstimated);
  }
  await slack.submitModal();
}

export async function deleteQuickRunRequest(
  slack: SlackPage,
  quickRunText?: string
): Promise<void> {
  // Open edit modal, then click "Supprimer ma demande"
  await slack.clickButton('Ajouter', quickRunText);
  await slack.waitForModal();
  await slack.clickButton('Supprimer ma demande');
  // Slack shows a confirm dialog — click the confirm button
  await slack.wait(500);
  const confirmBtn = slack.page.locator('[data-qa="dialog_go_btn"]').first();
  if (await confirmBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
    await confirmBtn.click({ force: true });
  }
  await slack.wait(2000);
}

export async function lockQuickRun(slack: SlackPage, quickRunText?: string): Promise<void> {
  // Runner actions are posted as thread ephemeral — try UI first, fallback to API
  const threadText = quickRunText || 'Quick Run';
  try {
    await slack.openThread(threadText);
    await slack.wait(1000);
    const jePartsBtn = slack.page.locator('button:has-text("Je pars")').first();
    if (await jePartsBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await jePartsBtn.click({ force: true });
      await slack.wait(2000);
      await slack.closeThread();
      return;
    }
    await slack.closeThread();
  } catch {
    // Thread might not open if no visible replies
  }

  // Fallback: lock via backend API (ephemeral thread messages are unreliable)
  await lockLatestQuickRun();
  await slack.reload();
  await slack.wait(3000);
}

export async function closeQuickRunAction(slack: SlackPage, quickRunText?: string): Promise<void> {
  // Runner actions are posted as thread ephemeral — try UI first, fallback to API
  const threadText = quickRunText || 'Quick Run';
  try {
    await slack.openThread(threadText);
    await slack.wait(1000);
    const cloturerBtn = slack.page.locator('button:has-text("Cloturer")').first();
    if (await cloturerBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await cloturerBtn.click({ force: true });
      await slack.wait(2000);
      await slack.closeThread();
      return;
    }
    await slack.closeThread();
  } catch {
    // Thread might not open if no visible replies
  }

  // Fallback: close via backend API (ephemeral thread messages are unreliable)
  await closeLatestQuickRun();
  await slack.reload();
  await slack.wait(3000);
}

// ── Vendor management ────────────────────────────────────────

export async function createVendor(
  slack: SlackPage,
  name: string,
  fulfillmentTypes: string[] = ['pickup']
): Promise<void> {
  // "Ajouter une enseigne" is in the channel kickoff message, not the dashboard modal.
  // Dismiss any open modal first so we can interact with the channel.
  if (await slack.isModalVisible()) {
    await slack.dismissModal();
  }
  await slack.clickButton('Ajouter une enseigne');
  await slack.waitForModal();
  await slack.fillModalField('name', name);
  for (const ft of fulfillmentTypes) {
    await slack.checkModalCheckbox('fulfillment_types', ft);
  }
  await slack.submitModal();
}

// ── Multi-user orchestration helpers ─────────────────────────

/**
 * Refresh all Slack pages and wait for Slack to settle.
 * Useful after actions that update messages in the channel.
 */
export async function refreshAll(...slackPages: SlackPage[]): Promise<void> {
  await Promise.all(slackPages.map((s) => s.reload()));
  await slackPages[0]?.wait(2_000);
}

/**
 * Open dashboard for multiple users sequentially.
 * Sequential because /lunch triggers server-side state.
 */
export async function openDashboardAll(...slackPages: SlackPage[]): Promise<void> {
  for (const slack of slackPages) {
    await openDashboard(slack);
  }
}
