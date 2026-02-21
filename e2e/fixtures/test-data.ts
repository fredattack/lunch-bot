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
} as const;

export const TestOrders = {
  MARGHERITA: {
    description: 'Margherita classique',
    priceEstimated: '12',
  },
  CALZONE: {
    description: 'Calzone jambon fromage',
    priceEstimated: '14',
  },
  CALIFORNIA_ROLL: {
    description: 'California Roll x8',
    priceEstimated: '9.50',
  },
  CHEESEBURGER: {
    description: 'Cheeseburger menu complet',
    priceEstimated: '11,50',
  },
} as const;

export const TestQuickRun = {
  BOULANGERIE: {
    destination: 'Boulangerie du coin',
    delayMinutes: '30',
  },
  REQUEST_PAIN: {
    description: 'Pain aux cereales',
    priceEstimated: '3',
  },
  REQUEST_CROISSANT: {
    description: 'Croissants x2',
    priceEstimated: '4',
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
