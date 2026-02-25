# Lunch Bot - Captures d'ecran

Captures extraites des tests Playwright E2E (screenshot: 'on') executes contre la vraie interface web Slack (sandbox developpeur hddevtest).

## Ecrans captures

### 01 - Dashboard S1 "En cours" (aucune proposition)
**Fichier :** `01-dashboard-s1-en-cours.png`

La modale principale du bot ("Lunch-bot - Laravel") dans l'etat S1 : session ouverte, aucune commande en cours.
- Date du jour (sam. 21/02) et statut "En cours"
- Boutons "Voir les restaurants" et "Quick Run"
- Message "Aucune commande n'a ete lancee aujourd'hui."
- CTA : "Demarrer une commande" (vert) et "Proposer un nouveau restaurant"
- Bouton "Fermer"

C'est l'ecran d'accueil apres `/lunch` quand aucune proposition n'existe encore.

---

### 02 - Dashboard S1 "Verrouillée"
**Fichier :** `02-dashboard-s1-verrouille.png`

Meme modale S1 mais avec le statut "Verrouillée" (session lockee).
La session est automatiquement verrouillee apres expiration de la deadline.
Les utilisateurs reguliers ne peuvent plus passer de nouvelles commandes.
On voit en arriere-plan le badge "1 nouveau message" sur un Quick Run.

---

### 03 - Modal "Demarrer une commande"
**Fichier :** `03-modal-demarrer-commande.png`

Formulaire de creation de commande groupee (propose un restaurant du catalogue).
Champs :
- **Restaurant** : dropdown + bouton "Nouveau restaurant"
- **Type** : dropdown (A Emporter / Livraison)
- **Mode** : "Commande groupee" (lecture seule)
- **Deadline (indicative)** : champ horaire pre-rempli (11:30)
- **Remarque (optionnel)** : zone de texte libre
- **Besoin d'aide ?** : checkbox delegation
- Boutons "Annuler" / "Continuer"

---

### 04 - Modal "Proposer un restaurant"
**Fichier :** `04-modal-proposer-restaurant.png`

Formulaire de creation d'un nouveau restaurant (vendor) inline lors d'une proposition.
Champs :
- **Nom du restaurant** : texte libre (placeholder "Ex: Sushi Wasabi")
- **Website** : URL optionnel
- **Types disponibles** : checkboxes (A emporter, Livraison, Sur place)
- **Options de commande** : checkbox "Autoriser les commandes individuelles"
- **Deadline (indicatif)** : champ horaire (11:30)
- **Remarque (optionnel)** : zone de texte
- Boutons "Annuler" / "Continuer"

---

### 05 - Modal "Proposer un restaurant" - Erreur de validation
**Fichier :** `05-modal-proposer-restaurant-erreur.png`

Meme modal que 04 mais avec une erreur de validation visible :
- Le champ "Nom du restaurant" a une bordure rouge
- Message d'erreur : "Veuillez remplir ce champ obligatoire."

Demontre la validation cote Slack Block Kit lors de la soumission sans nom.

---

### 06 - Modal "Quick Run"
**Fichier :** `06-modal-quick-run-creation.png`

Formulaire de creation d'un Quick Run (course rapide individuelle).
Champs :
- **Ou allez-vous ?** : texte libre (placeholder "Ex: Boulangerie du coin, Starbucks...")
- **Delai (en minutes)** : champ numerique pre-rempli (10)
- **Note (optionnel)** : zone de texte ("Infos supplementaires...")
- Boutons "Annuler" / "Lancer" (vert)

---

### 07 - Channel #food - Messages Quick Run
**Fichier :** `07-channel-quick-run-messages.png`

Vue du canal Slack `#food` montrant plusieurs messages Quick Run postes par le bot.
Chaque message affiche :
- Titre "Quick Run" avec le runner (`@beber va chez Boulangerie du coin`)
- Deadline et statut ("17:18 | Statut: Ouvert")
- "Aucune demande pour le moment."
- Bouton vert "Ajouter une demande"

---

### 08 - Channel #food - Message ephemere d'erreur
**Fichier :** `08-channel-erreur-ephemeral.png`

Vue du canal montrant un message ephemere Slack (visible uniquement par l'utilisateur) :
- Label "Visible par vous seulement"
- Message : "Echec de la commande /lunch avec l'erreur dispatch_failed"
- L'utilisateur tentait de taper `/lunch` dans le compositeur

Illustre le feedback d'erreur que recoit un utilisateur quand le bot ne repond pas.

---

### 09 - Dashboard S3 "Ma commande"
**Fichier :** `09-dashboard-s3-ma-commande.png`

Dashboard dans l'etat S3 : l'utilisateur a passe une commande sur une proposition active.
- Date du jour (dim. 22/02) et statut "En cours - Deadline 11:30"
- Proposition : "Tatie Crouton — A emporter / 1 commande(s) · 12.00 EUR"
- "Ta commande : Margherita classique — 12.00 EUR"
- Boutons "Modifier" (vert) et "Voir le recap"
- Bouton "Fermer"

C'est l'ecran que voit un utilisateur qui a deja passe commande sur une proposition.

---

### 10 - Dashboard S4 "En charge"
**Fichier :** `10-dashboard-s4-en-charge.png`

Dashboard dans l'etat S4 : l'utilisateur est runner/orderer d'une proposition active.
- Date du jour (sam. 21/02) et statut "En cours"
- "Commande en cours - prise en charge par vous"
- "Tatie Crouton | 1 commande(s) | Ouverte"
- Boutons "Voir le recapitulatif" et "Cloturer cette commande" (rose/rouge)
- Boutons "Voir les restaurants" et "Quick Run"

C'est l'ecran que voit le responsable (runner ou orderer) pour gerer sa proposition.

---

### 11 - Modal "Nouvelle commande"
**Fichier :** `11-modal-nouvelle-commande.png`

Formulaire de creation/edition d'une commande individuelle.
Champs :
- **Description** : texte libre (ex: "Margherita classique")
- **Prix estime (facultatif)** : champ texte
- **Notes (facultatif)** : zone de texte multiline
- Boutons "Annuler" / "Commander" (vert)

Ce modal s'ouvre apres avoir selectionne un restaurant (via "Commander ici" ou apres proposition).

---

### 12 - Channel #food - Messages de proposition
**Fichier :** `12-channel-message-proposition.png`

Vue du canal Slack `#food` montrant les messages de proposition postes par le bot.
Chaque message affiche :
- "lunch-bot-local" avec timestamp
- "Nouvelle commande lancee"
- "Tatie Crouton – pickup / Par @beber"
- Boutons "Commander" (vert) et "Autre enseigne"
- Indicateur "1 reponse" pour le thread

Montre le flux de propositions tel qu'il apparait dans le canal pour tous les utilisateurs.

### 13 - Modal "Modifier commande"
**Fichier :** `13-modal-modifier-commande.png`

Formulaire d'edition d'une commande existante (push modal depuis le dashboard S3).
Champs :
- **Description** : texte pre-rempli (ex: "Quatre Fromages")
- **Prix estime (facultatif)** : pre-rempli (ex: "15")
- **Notes (facultatif)** : zone de texte multiline
- **Prix final (facultatif)** : visible si runner/orderer
- Bouton "Supprimer ma commande" (rouge)
- Boutons "Annuler" / "Enregistrer"

Ce modal differe du formulaire de creation (11) par le titre "Modifier commande", le champ "Prix final" supplementaire et le bouton de suppression.

---

### 14 - Modal "Ajouter une demande" (Quick Run)
**Fichier :** `14-modal-ajouter-demande-quickrun.png`

Formulaire d'ajout d'une demande sur un Quick Run existant.
Champs :
- **Quick Run** : contexte affiche ("Boulangerie du coin par @beber")
- **Que voulez-vous ?** : texte libre (ex: "Pain aux cereales")
- **Prix estime (facultatif)** : champ texte (ex: "3")
- Boutons "Annuler" / "Ajouter" (vert)

Ce modal s'ouvre depuis le bouton "Ajouter une demande" sur un message Quick Run dans le canal.

---

### 15 - Thread Quick Run - Gestion du runner
**Fichier :** `15-thread-quickrun-gestion.png`

Vue du fil de discussion (thread) d'un message Quick Run, cote runner.
Elements visibles :
- Message ephemere "Gestion Quick Run — actions reservees au runner"
- Boutons "Recapitulatif" et "Je pars" (vert)
- Notification "@Kamal a ajoute une demande."
- Canal #food en arriere-plan avec le Quick Run et "Demandes: 1 (@Kamal)"

C'est l'interface de gestion que voit le runner dans le thread du Quick Run.

---

### 16 - Channel #food - Quick Run verrouille
**Fichier :** `16-channel-quickrun-verrouille.png`

Vue du canal Slack `#food` montrant des Quick Runs en differents statuts apres verrouillage.
- Quick Run "Statut: Ouvert" avec "Demandes: 1 (@Kamal)" et bouton "Ajouter une demande"
- Quick Run "Statut: Verrouille" avec "Demandes: 1 (@Kamal)" (plus de bouton d'ajout)
- Quick Run "Statut: Verrouille" sans demandes
- Indicateurs "1 reponse" sur les threads

Montre la difference visuelle entre un Quick Run ouvert et verrouille.

---

### 17 - Channel #food - Quick Run cloture
**Fichier :** `17-channel-quickrun-cloture.png`

Vue du canal montrant des Quick Runs en differents statuts de cycle de vie.
- Quick Run "Statut: Verrouille" avec demandes
- Quick Run "Statut: Ouvert" avec bouton "Ajouter une demande"
- Quick Run "Statut: Cloture" sans demandes

Montre les trois etats possibles d'un Quick Run dans le canal.

---

## Inventaire complet des ecrans du bot

Les 17 captures ci-dessus couvrent les ecrans principaux atteints lors des runs de test.
Voici l'inventaire complet de tous les ecrans testes par la suite E2E (44+ tests, 9 phases) :

### Modales (Block Kit)

| Ecran | Capture | Phase | Description | Test a lancer |
|-------|---------|-------|-------------|---------------|
| Dashboard S1 | 01, 02 | 1, 7 | Aucune proposition - "Demarrer" / "Proposer" | E2E-1.1 `01-dashboard-open.spec.ts` |
| Dashboard S2 | - | 7 | Propositions ouvertes - boutons "Commander" | E2E-7.2 `02-state-s2-open-proposals.spec.ts` |
| Dashboard S3 | 09 | 7 | Ma commande - details + "Modifier" | E2E-7.3 `03-state-s3-has-order.spec.ts` |
| Dashboard S4 | 10 | 7 | En charge (runner) - "Recapitulatif" / "Cloturer" | E2E-7.4 `04-state-s4-in-charge.spec.ts` |
| Dashboard S5 | - | 7 | Tout cloture - "Relancer" | E2E-7.5 `05-state-s5-all-closed.spec.ts` |
| Dashboard S6 | - | 7 | Historique (lecture seule) | E2E-7.6 `06-state-s6-history.spec.ts` |
| Demarrer une commande | 03 | 1, 2 | Proposition depuis le catalogue | E2E-2.1 `01-propose-from-catalog.spec.ts` |
| Proposer un restaurant | 04, 05 | 2 | Creation vendor inline | E2E-2.2 `02-propose-new-restaurant.spec.ts` |
| Formulaire de commande | 11 | 3 | Description + prix estime | E2E-3.1 `01-create-order.spec.ts` |
| Edition de commande | 13 | 3 | Formulaire pre-rempli + "Supprimer" | E2E-3.2 `02-edit-order.spec.ts` |
| Confirmation suppression | - | 3 | Dialog "Oui" / "Confirmer" | E2E-3.3 `03-delete-order.spec.ts` |
| Ajustement de prix | - | 3 | Selection commande + prix final | E2E-3.4 `04-adjust-price.spec.ts` |
| Recapitulatif | - | 2, 9 | Liste des commandes d'une proposition | E2E-9.1 `01-happy-path-group-order.spec.ts` |
| Delegation de role | - | 2 | User-select pour deleguer | E2E-2.4 `04-delegate-role.spec.ts` |
| Quick Run creation | 06 | 4 | Destination + delai + note | E2E-4.1 `01-create-quickrun.spec.ts` |
| Quick Run demande | 14 | 4 | Description + prix d'une demande | E2E-4.2 `02-add-request.spec.ts` |
| Quick Run recap | - | 4 | Resume avec totaux | E2E-4.5 `05-close-quickrun.spec.ts` |
| Vendor list | - | 5 | Liste des restaurants + recherche | E2E-5.3 `03-vendor-list-search.spec.ts` |
| Vendor edit | - | 5 | Edition nom + types + actif | E2E-5.2 `02-edit-vendor.spec.ts` |
| Vendor creation | - | 5 | Nom + types de fulfillment | E2E-5.1 `01-create-vendor.spec.ts` |

### Messages dans le canal

| Ecran | Capture | Phase | Description | Test a lancer |
|-------|---------|-------|-------------|---------------|
| Message Quick Run | 07, 16, 17 | 4 | Destination, deadline, statut, "Ajouter" | E2E-4.1 `01-create-quickrun.spec.ts` |
| Thread Quick Run (runner) | 15 | 4 | Gestion runner, "Recapitulatif" / "Je pars" | E2E-4.5 `05-close-quickrun.spec.ts` |
| Message proposition | 12 | 2 | Restaurant, roles, claim/deleguer | E2E-2.1 `01-propose-from-catalog.spec.ts` |
| Thread "Nouvelle commande" | - | 3 | Reply thread apres 1ere commande | E2E-3.1 `01-create-order.spec.ts` |
| Message ephemere | 08 | 1-8 | Erreurs, confirmations, rejets | E2E-8.1 `01-locked-session-actions.spec.ts` |
| Resume de cloture | - | 1 | Recap apres fermeture session | E2E-1.4 `04-session-close.spec.ts` |

## Resultats de la suite de tests

Run avec `screenshot: 'on'` - 100 tests, 1 worker serie.

**Tests reussis (ecrans captures):**
- Phase 1 : Dashboard open (4/4), Session create (1/2), Session lock (2/2)
- Phase 2 : Propose catalog (1/4), Propose new (1/3), Delegate (1/2)
- Phase 3 : Edit order (1/2), Delete order (1/2)
- Phase 5 : Vendor edit/search/deactivate (3/3)

**Tests en echec (principalement `dispatch_failed` / `operation_timeout`):**
- Les phases 2-4, 6-9 echouent largement a cause de timeouts Slack
- Les screenshots d'echec montrent le canal ou le dashboard S1 (l'ecran cible n'est jamais atteint)

## Ecrans manquants

Les ecrans suivants ne sont pas encore captures :

**Dashboards :**
- Dashboard S2 (propositions ouvertes - "Commander ici")
- Dashboard S5 (tout cloture - "Relancer")
- Dashboard S6 (historique - lecture seule)

**Modales :**
- Confirmation de suppression
- Ajustement de prix final
- Recapitulatif des commandes
- Delegation de role
- Quick Run recap (modal recapitulatif)
- Vendor list / creation

**Messages canal :**
- Thread "Nouvelle commande"
- Resume de cloture

Pour capturer ces ecrans, il faudrait lancer les phases correspondantes (voir colonne "Test a lancer" dans l'inventaire).