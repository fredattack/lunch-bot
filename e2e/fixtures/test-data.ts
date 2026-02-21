/**
 * Test data constants used across E2E tests.
 * These values should match what is seeded in the test database.
 */

export const TestVendors = {
  PIZZA_PLACE: {
    name: 'Pizza Place Test',
    cuisineType: 'pizza',
  },
  SUSHI_BAR: {
    name: 'Sushi Bar Test',
    cuisineType: 'sushi',
  },
  BURGER_JOINT: {
    name: 'Burger Joint Test',
    cuisineType: 'burger',
  },
  THAI_EXPRESS: {
    name: 'Thai Express Test',
    cuisineType: 'thai',
  },
} as const;

export const TestOrders = {
  // User A orders
  MARGHERITA: {
    description: 'Margherita classique',
    priceEstimated: '12',
  },
  SALMON_ROLL: {
    description: 'Salmon Roll x6',
    priceEstimated: '8',
  },
  // User B orders
  CALZONE: {
    description: 'Calzone jambon fromage',
    priceEstimated: '14',
  },
  CALIFORNIA_ROLL: {
    description: 'California Roll x8',
    priceEstimated: '9.50',
  },
  // User C orders
  CHEESEBURGER: {
    description: 'Cheeseburger menu complet',
    priceEstimated: '11,50',
  },
  PAD_THAI: {
    description: 'Pad Thai crevettes',
    priceEstimated: '13',
  },
  // Admin orders
  QUATRE_FROMAGES: {
    description: 'Pizza Quatre Fromages',
    priceEstimated: '15',
  },
  EDAMAME: {
    description: 'Edamame + Miso',
    priceEstimated: '7',
  },
} as const;

export const TestQuickRun = {
  BOULANGERIE: {
    destination: 'Boulangerie du coin',
    delayMinutes: '30',
  },
  CAFE: {
    destination: 'Cafe de la place',
    delayMinutes: '15',
  },
  REQUEST_PAIN: {
    description: 'Pain aux cereales',
    priceEstimated: '3',
  },
  REQUEST_CROISSANT: {
    description: 'Croissants x2',
    priceEstimated: '4',
  },
  REQUEST_CAFE: {
    description: 'Cafe allonge',
    priceEstimated: '2.50',
  },
  REQUEST_CHOCOLATINE: {
    description: 'Chocolatine x3',
    priceEstimated: '4.50',
  },
} as const;

export const TestPrices = {
  VALID_DOT: '12.50',
  VALID_COMMA: '12,50',
  VALID_INTEGER: '12',
  INVALID_LETTERS: 'abc',
  ZERO: '0',
  FINAL_ADJUSTED: '11',
} as const;

export const DashboardLabels = {
  S1: 'Aucune commande',
  S2: 'Commandes ouvertes',
  S3: 'Ma commande',
  S4: 'En charge',
  S5: 'Tout cloture',
  S6: 'Historique',
} as const;

export const ErrorMessages = {
  ORDERS_LOCKED: 'Les commandes sont verrouillees',
  SESSION_CLOSED: 'La journee est cloturee',
  DESCRIPTION_REQUIRED: 'Description requise',
  PRICE_ESTIMATED_INVALID: 'Prix estime invalide',
  PRICE_FINAL_INVALID: 'Prix final invalide',
  VENDOR_NAME_REQUIRED: 'Nom du restaurant requis',
  FULFILLMENT_REQUIRED: 'Au moins un type doit etre selectionne',
  DESTINATION_REQUIRED: 'Destination requise',
  DELAY_INVALID: 'Le delai doit etre entre 1 et 120 minutes',
  VENDOR_INVALID: 'Enseigne invalide',
  ROLE_ALREADY_ASSIGNED: 'Role deja attribue',
  RESPONSIBLE_ALREADY_ASSIGNED: 'Un responsable est deja assigne',
  ONLY_RUNNER_CAN_LOCK: 'Seul le runner peut verrouiller',
  ONLY_RUNNER_CAN_CLOSE: 'Seul le runner peut cloturer',
  ONLY_RESPONSIBLE_CAN_CLOSE: 'Seul le responsable peut cloturer',
  ONLY_RESPONSIBLE_CAN_VIEW: 'Seul le responsable peut voir le recapitulatif',
  QUICKRUN_NO_MORE_REQUESTS: "Ce Quick Run n'accepte plus de demandes",
  ORDER_DELETED: 'Commande supprimee',
  ORDER_SAVED: 'Commande enregistree',
  ORDER_UPDATED: 'Commande mise a jour',
  PRICE_UPDATED: 'Prix final mis a jour',
  NOT_YOUR_ROLE: "Vous n'etes pas",
  NO_ROLE_TO_DELEGATE: "Vous n'avez pas de role a deleguer",
} as const;

/**
 * Test user display names — must match the Slack workspace users.
 * Used for assertions like "X a commande..." or delegation selectors.
 */
export const TestUsers = {
  USER_A: {
    displayName: process.env.SLACK_TEST_USER_A_DISPLAY_NAME || 'User A',
    id: process.env.SLACK_TEST_USER_A_ID || 'UXXXXXXA',
  },
  USER_B: {
    displayName: process.env.SLACK_TEST_USER_B_DISPLAY_NAME || 'User B',
    id: process.env.SLACK_TEST_USER_B_ID || 'UXXXXXXB',
  },
  USER_C: {
    displayName: process.env.SLACK_TEST_USER_C_DISPLAY_NAME || 'User C',
    id: process.env.SLACK_TEST_USER_C_ID || 'UXXXXXXC',
  },
  ADMIN: {
    displayName: process.env.SLACK_TEST_ADMIN_DISPLAY_NAME || 'Admin',
    id: process.env.SLACK_TEST_ADMIN_ID || 'UXXXXXXADMIN',
  },
} as const;
