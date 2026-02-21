import { SlackPage } from '../fixtures/slack-page';

/**
 * Reusable higher-level Slack actions composed from SlackPage primitives.
 * Each function performs a complete user workflow step.
 */

// ── Dashboard ────────────────────────────────────────────────

export async function openDashboard(slack: SlackPage): Promise<void> {
  await slack.sendSlashCommand('/lunch');
  await slack.waitForModal();
}

// ── Proposal from catalog ────────────────────────────────────

export async function proposeFromCatalog(
  slack: SlackPage,
  vendorName: string,
  fulfillment: 'pickup' | 'delivery' = 'pickup'
): Promise<void> {
  await slack.clickButton('Demarrer une commande');
  await slack.waitForModal();
  await slack.selectModalOption('enseigne', vendorName);
  if (fulfillment === 'delivery') {
    await slack.selectModalOption('fulfillment', 'Livraison');
  }
  await slack.submitModal();
  // Modal transitions to order form
  await slack.waitForModal();
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

// ── Claim role ───────────────────────────────────────────────

export async function claimRole(
  slack: SlackPage,
  role: 'runner' | 'orderer',
  proposalText: string
): Promise<void> {
  const buttonText = role === 'runner' ? 'Je suis runner' : 'Je suis orderer';
  await slack.clickButton(buttonText, proposalText);
}

// ── Delegate role ────────────────────────────────────────────

export async function delegateRole(
  slack: SlackPage,
  targetUserName: string,
  proposalText: string
): Promise<void> {
  await slack.clickButton('Deleguer', proposalText);
  await slack.waitForModal();
  await slack.selectUser('delegate', targetUserName);
  await slack.submitModal();
}

// ── Adjust final price ───────────────────────────────────────

export async function adjustFinalPrice(
  slack: SlackPage,
  orderUserText: string,
  finalPrice: string,
  proposalText: string
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
  proposalText: string
): Promise<void> {
  await slack.clickButton('Cloturer', proposalText);
}

// ── Close session ────────────────────────────────────────────

export async function closeSession(
  slack: SlackPage,
  sessionText: string
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
  await slack.fillModalField('destination', destination);
  await slack.fillModalField('delay', delayMinutes);
  if (note) {
    await slack.fillModalField('note', note);
  }
  await slack.submitModal();
}

export async function addQuickRunRequest(
  slack: SlackPage,
  description: string,
  priceEstimated?: string,
  quickRunText?: string
): Promise<void> {
  await slack.clickButton('Ajouter', quickRunText);
  await slack.waitForModal();
  await slack.fillModalField('description', description);
  if (priceEstimated) {
    await slack.fillModalField('price_estimated', priceEstimated);
  }
  await slack.submitModal();
}

// ── Vendor management ────────────────────────────────────────

export async function createVendor(
  slack: SlackPage,
  name: string,
  fulfillmentTypes: string[] = ['pickup']
): Promise<void> {
  await slack.clickButton('Ajouter une enseigne');
  await slack.waitForModal();
  await slack.fillModalField('name', name);
  for (const ft of fulfillmentTypes) {
    await slack.checkModalCheckbox('fulfillment_types', ft);
  }
  await slack.submitModal();
}
