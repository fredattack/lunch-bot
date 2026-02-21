# Manifeste de Test - Slack Lunch Bot MVP

**Date** : 21 fevrier 2026
**Objectif** : Documenter exhaustivement les scenarios de test du Lunch Bot MVP, couvrant toutes les interactions Slack, les machines a etats, les flux utilisateur, les permissions et les cas limites.

---

## Table des matieres

1. [Outils recommandes](#1-outils-recommandes)
2. [Interactions Slack & Boutons](#2-interactions-slack--boutons)
3. [Etats & Transitions des machines a etats](#3-etats--transitions)
4. [Roles & Permissions](#4-roles--permissions)
5. [Dashboard : Etats & UI](#5-dashboard-etats--ui)
6. [Flux de commande (Order)](#6-flux-de-commande)
7. [Flux de proposition (Proposal)](#7-flux-de-proposition)
8. [Gestion des prix](#8-gestion-des-prix)
9. [Quick Run](#9-quick-run)
10. [Gestion des restaurants (Vendor)](#10-gestion-des-restaurants)
11. [Taches planifiees](#11-taches-planifiees)
12. [Multi-tenant / Organisation](#12-multi-tenant)
13. [Cas limites & erreurs](#13-cas-limites--erreurs)
14. [Scenarios end-to-end critiques](#14-scenarios-end-to-end)
15. [Couverture existante vs. manquante](#15-couverture-existante-vs-manquante)

---

## 1. Outils recommandes

### 1.1 Stack de test actuel

| Outil | Usage | Fichier de config |
|-------|-------|-------------------|
| **PHPUnit 11** | Tests unitaires et feature | `phpunit.xml` |
| **Laravel Test Helpers** | HTTP tests, mocking, factories | `tests/TestCase.php` |
| **Laravel Pint** | Linting / formatage | `pint.json` |

### 1.2 Outils recommandes pour renforcer la couverture

#### Tests backend (priorite 1)

| Outil | Pourquoi | Installation |
|-------|----------|-------------|
| **PHPUnit (existant)** | Deja en place, couvre Actions, Models, Policies. Renforcer la couverture des Handlers Slack. | Deja installe |
| **Laravel HTTP Tests** | Tester les endpoints `/api/slack/events` et `/api/slack/interactivity` avec des payloads reels. | Natif Laravel |
| **Mockery** | Mocker `SlackService`, `SlackMessenger` pour tester les handlers sans appels HTTP reels. | Deja disponible via Laravel |

**Strategie** : L'essentiel des tests manquants concerne la **couche Slack Handlers** (interaction handler dispatch, view submissions, block actions). Ces tests doivent simuler des payloads Slack et verifier les effets de bord (creation de modeles, appels messenger).

#### Tests d'integration Slack (priorite 2)

| Outil | Pourquoi | Lien |
|-------|----------|------|
| **Slack Bolt Test Helpers** | Simuler des payloads Slack signes pour tester le middleware `slack.signature` en conditions reelles. | Generer manuellement avec `hash_hmac` |
| **Replay de payloads** | Capturer des payloads reels via `ngrok` + `Log::info()`, puis les rejouer dans les tests. | Pattern deja utilisable |

#### Tests end-to-end (priorite 3 - optionnel)

| Outil | Pourquoi | Lien |
|-------|----------|------|
| **Playwright** | Tester le dashboard web si une UI web est ajoutee. Pour le MVP (Slack-only), pas necessaire. | `npm install -D @playwright/test` |
| **Slack Test Workspace** | Workspace Slack dedie aux tests manuels avec le bot installe. | Configuration Slack App |

#### Qualite & CI

| Outil | Pourquoi | Installation |
|-------|----------|-------------|
| **PHPStan / Larastan** | Analyse statique pour detecter les erreurs de types avant runtime. | `composer require --dev larastan/larastan` |
| **Laravel Pint** | Deja en place. Executer `vendor/bin/pint --dirty` avant chaque commit. | Deja installe |

### 1.3 Recommandation d'architecture de test

```
tests/
├── Unit/
│   ├── Actions/           # ✅ Bien couvert (toutes les Actions)
│   ├── Authorization/     # ✅ Actor test
│   ├── Enums/             # ✅ DashboardState, SlackAction, OrderingMode
│   ├── Models/            # ✅ Relations, scopes, concerns
│   └── Services/Slack/    # ⚠️ Partiellement couvert (manque handlers)
├── Feature/
│   ├── Actions/           # ✅ Slack emoji sync
│   ├── Http/              # ⚠️ Controller basique, middleware OK
│   ├── Middleware/        # ✅ Signature, Organization, LogRequest
│   ├── Policies/          # ✅ Order, Vendor, VendorProposal
│   └── Workflows/         # ⚠️ LunchSessionWorkflow basique
└── Integration/           # 🔴 A creer
    ├── SlackHandlers/     # Handlers avec payloads Slack simules
    └── EndToEnd/          # Scenarios complets multi-etapes
```

---

## 2. Interactions Slack & Boutons

### 2.1 Block Actions (boutons dans les messages du channel)

Chaque action est identifiee par son `action_id` dans l'enum `SlackAction`.

#### Actions legacy (messages dans le channel)

| # | Action ID | Enum | Handler | Methode | Test recommande |
|---|-----------|------|---------|---------|-----------------|
| 1 | `open_proposal_modal` | `OpenProposalModal` | `ProposalInteractionHandler` | `openProposalModal()` | Ouvre le modal de proposition depuis le channel |
| 2 | `open_add_enseigne_modal` | `OpenAddEnseigneModal` | `VendorInteractionHandler` | Block action vendor | Ouvre le modal d'ajout d'enseigne |
| 3 | `close_day` | `CloseDay` | `SessionInteractionHandler` | `closeSession()` | Cloture la session (runner/orderer/admin only) |
| 4 | `claim_runner` | `ClaimRunner` | `ProposalInteractionHandler` | `claimRole()` | Assigne le role runner avec lock transactionnel |
| 5 | `claim_orderer` | `ClaimOrderer` | `ProposalInteractionHandler` | `claimRole()` | Assigne le role orderer avec lock transactionnel |
| 6 | `open_order_modal` | `OpenOrderModal` | `OrderInteractionHandler` | `openOrderModal()` | Ouvre le modal de commande |
| 7 | `open_edit_order_modal` | `OpenEditOrderModal` | `OrderInteractionHandler` | `openEditOrderModal()` | Ouvre le modal d'edition avec pre-remplissage |
| 8 | `open_summary` | `OpenSummary` | `ProposalInteractionHandler` | `openSummary()` | Affiche le recapitulatif (runner/orderer only) |
| 9 | `open_delegate_modal` | `OpenDelegateModal` | `ProposalInteractionHandler` | `openDelegateModal()` | Ouvre le modal de delegation de role |
| 10 | `open_adjust_price_modal` | `OpenAdjustPriceModal` | `ProposalInteractionHandler` | `openAdjustPriceModal()` | Ouvre le modal d'ajustement de prix final |
| 11 | `open_manage_enseigne_modal` | `OpenManageEnseigneModal` | `VendorInteractionHandler` | Block action vendor | Ouvre le modal de gestion d'enseigne |

#### Actions Dashboard (modals)

| # | Action ID | Enum | Handler | Methode | Test recommande |
|---|-----------|------|---------|---------|-----------------|
| 12 | `dashboard.create_proposal` | `DashboardCreateProposal` | `ProposalInteractionHandler` | `createProposal()` | Ouvre le modal "nouveau restaurant" |
| 13 | `dashboard.start_from_catalog` | `DashboardStartFromCatalog` | `ProposalInteractionHandler` | `startFromCatalog()` | Ouvre le catalogue ou fallback nouveau resto |
| 14 | `dashboard.join_proposal` | `DashboardJoinProposal` | `OrderInteractionHandler` | `joinProposal()` | Ouvre le modal de commande pour une proposition existante |
| 15 | `dashboard.relaunch` | `DashboardRelaunch` | `ProposalInteractionHandler` | `startFromCatalog()` | Relance une commande (state S5) |
| 16 | `dashboard.vendors_list` | `DashboardVendorsList` | `VendorInteractionHandler` | Block action vendor | Ouvre la liste des restaurants |

#### Actions Order

| # | Action ID | Enum | Handler | Methode | Test recommande |
|---|-----------|------|---------|---------|-----------------|
| 17 | `order.open_edit` | `OrderOpenEdit` | `OrderInteractionHandler` | `openEditOrder()` | Ouvre l'edition d'une commande existante (push modal) |
| 18 | `order.delete` | `OrderDelete` | `OrderInteractionHandler` | `deleteUserOrder()` | Supprime la commande avec validation proprietaire |

#### Actions Proposal (responsable)

| # | Action ID | Enum | Handler | Methode | Test recommande |
|---|-----------|------|---------|---------|-----------------|
| 19 | `proposal.open_manage` | `ProposalOpenManage` | `ProposalInteractionHandler` | `openManage()` | Ouvre la gestion de proposition |
| 20 | `proposal.open_recap` | `ProposalOpenRecap` | `ProposalInteractionHandler` | `openRecap()` | Affiche le recap avec totaux (runner/orderer only) |
| 21 | `proposal.close` | `ProposalClose` | `ProposalInteractionHandler` | `closeProposal()` | Cloture la proposition (runner/orderer only) |
| 22 | `proposal.set_status` | `ProposalSetStatus` | `ProposalInteractionHandler` | Non implemente | Changement de statut manuel |
| 23 | `proposal.take_charge` | `ProposalTakeCharge` | `ProposalInteractionHandler` | `takeCharge()` | Prendre en charge (assigne runner ou orderer) |

#### Actions Session

| # | Action ID | Enum | Handler | Methode | Test recommande |
|---|-----------|------|---------|---------|-----------------|
| 24 | `session.close` | `SessionClose` | `SessionInteractionHandler` | `closeSession()` | Cloture la session + recap |

#### Actions Quick Run

| # | Action ID | Enum | Handler | Methode | Test recommande |
|---|-----------|------|---------|---------|-----------------|
| 25 | `quickrun.open` | `QuickRunOpen` | `QuickRunInteractionHandler` | `openCreateModal()` | Ouvre le modal de creation |
| 26 | `quickrun.add_request` | `QuickRunAddRequest` | `QuickRunInteractionHandler` | `openRequestModal()` | Ouvre le modal d'ajout de demande |
| 27 | `quickrun.edit_request` | `QuickRunEditRequest` | `QuickRunInteractionHandler` | `openEditRequestModal()` | Edition de demande existante |
| 28 | `quickrun.delete_request` | `QuickRunDeleteRequest` | `QuickRunInteractionHandler` | `handleDeleteRequest()` | Suppression avec message ephemeral |
| 29 | `quickrun.lock` | `QuickRunLock` | `QuickRunInteractionHandler` | `handleLock()` | Verrouillage (runner only) |
| 30 | `quickrun.close` | `QuickRunClose` | `QuickRunInteractionHandler` | `handleClose()` | Cloture (runner only) |
| 31 | `quickrun.recap` | `QuickRunRecap` | `QuickRunInteractionHandler` | `openRecap()` | Recap avec totaux estimes/finaux |
| 32 | `quickrun.adjust_prices` | `QuickRunAdjustPrices` | `QuickRunInteractionHandler` | `openAdjustPrices()` | Ajustement prix (runner only) |

#### Actions Vendor List

| # | Action ID | Enum | Handler | Methode |
|---|-----------|------|---------|---------|
| 33 | `vendors_list.search` | `VendorsListSearch` | `VendorInteractionHandler` | Recherche en temps reel |
| 34 | `vendors_list.edit` | `VendorsListEdit` | `VendorInteractionHandler` | Edition d'un restaurant |

#### Actions Dev/Admin

| # | Action ID | Enum | Handler | Restriction |
|---|-----------|------|---------|-------------|
| 35 | `dev.reset_database` | `DevResetDatabase` | `VendorInteractionHandler` | Dev user + env local/dev/testing |
| 36 | `dev.export_vendors` | `DevExportVendors` | `VendorInteractionHandler` | Dev user + env local/dev/testing |

#### Actions de navigation (post-creation)

| # | Action ID | Enum | Handler | Methode |
|---|-----------|------|---------|---------|
| 37 | `open_order_for_proposal` | `OpenOrderForProposal` | `OrderInteractionHandler` | `openOrderForProposal()` |
| 38 | `open_lunch_dashboard` | `OpenLunchDashboard` | `SessionInteractionHandler` | `openDashboard()` |

### 2.2 View Submissions (callbacks modaux)

| # | Callback ID | Enum | Handler | Methode | Test recommande |
|---|-------------|------|---------|---------|-----------------|
| 1 | `proposal_create` | `CallbackProposalCreate` | `ProposalInteractionHandler` | `handleProposalSubmission()` | Cree VendorProposal + transition vers modal commande |
| 2 | `restaurant_propose` | `CallbackRestaurantPropose` | `ProposalInteractionHandler` | `handleRestaurantPropose()` | Cree Vendor + VendorProposal + upload fichier |
| 3 | `enseigne_create` | `CallbackEnseigneCreate` | `VendorInteractionHandler` | `handleVendorCreate()` | Cree un nouveau Vendor |
| 4 | `enseigne_update` | `CallbackEnseigneUpdate` | `VendorInteractionHandler` | `handleVendorUpdate()` | Met a jour un Vendor existant |
| 5 | `order_create` | `CallbackOrderCreate` | `OrderInteractionHandler` | `handleOrderCreate()` | Cree ou met a jour une commande |
| 6 | `order_edit` | `CallbackOrderEdit` | `OrderInteractionHandler` | `handleOrderEdit()` | Edition de commande avec validation |
| 7 | `order_adjust_price` | `CallbackOrderAdjustPrice` | `OrderInteractionHandler` | `handleAdjustPrice()` | Ajustement prix final (runner/orderer) |
| 8 | `role_delegate` | `CallbackRoleDelegate` | `ProposalInteractionHandler` | `handleRoleDelegate()` | Delegation de role avec verification |
| 9 | `order_delete` | `CallbackOrderDelete` | `OrderInteractionHandler` | Confirmation de suppression |
| 10 | `quickrun_create` | `CallbackQuickRunCreate` | `QuickRunInteractionHandler` | `handleQuickRunCreate()` | Creation Quick Run avec destination + delai |
| 11 | `quickrun_request_create` | `CallbackQuickRunRequestCreate` | `QuickRunInteractionHandler` | `handleRequestCreate()` | Ajout demande Quick Run |
| 12 | `quickrun_request_edit` | `CallbackQuickRunRequestEdit` | `QuickRunInteractionHandler` | `handleRequestEdit()` | Edition demande Quick Run |
| 13 | `quickrun_close` | `CallbackQuickRunClose` | `QuickRunInteractionHandler` | `handleQuickRunCloseSubmission()` | Cloture avec ajustement prix |

### 2.3 Routage des interactions

Le dispatch se fait dans `SlackInteractionHandler::handleBlockActions()` via les methodes de categorisation de `SlackAction` :

```
isSession()   → SessionInteractionHandler
isOrder()     → OrderInteractionHandler
isDev()       → VendorInteractionHandler
isVendor()    → VendorInteractionHandler
isQuickRun()  → QuickRunInteractionHandler
isProposal()  → ProposalInteractionHandler
```

**Tests a ecrire :**
- [ ] Verifier que chaque `action_id` est correctement route vers le bon handler
- [ ] Verifier que les `action_id` inconnus sont ignores (log warning)
- [ ] Verifier que chaque `callback_id` de view_submission est correctement route
- [ ] Verifier le fallback `default => response('', 200)` pour les callbacks inconnus

---

## 3. Etats & Transitions

### 3.1 LunchSession : Machine a etats

```
┌──────┐   deadline_at ≤ now   ┌────────┐   manual    ┌────────┐
│ Open │ ─────────────────────→│ Locked │ ───────────→│ Closed │
└──────┘                       └────────┘             └────────┘
    │                                                      ↑
    └──────────────── manual (close_day) ──────────────────┘
```

| Transition | Declencheur | Action | Conditions |
|-----------|-------------|--------|------------|
| Open → Locked | `LockExpiredSessions` (chaque minute) | `$session->update(['status' => 'locked'])` | `deadline_at ≤ now()` ET `status = open` |
| Open → Closed | `CloseLunchSession` (manuel) | Ferme session + toutes propositions | Runner/orderer/admin |
| Locked → Closed | `CloseLunchSession` (manuel) | Ferme session + toutes propositions | Runner/orderer/admin |

**Tests :**
- [ ] T3.1.1 : Open → Locked quand deadline depasse
- [ ] T3.1.2 : Open → Closed par runner
- [ ] T3.1.3 : Open → Closed par admin
- [ ] T3.1.4 : Locked → Closed par orderer
- [ ] T3.1.5 : Closed est un etat terminal (pas de transition sortante)
- [ ] T3.1.6 : Tentative de modification sur session Locked par user normal → erreur
- [ ] T3.1.7 : Modification sur session Locked par admin → autorise
- [ ] T3.1.8 : Fermeture de session ferme toutes les propositions associees

### 3.2 ProposalStatus : Machine a etats

```
┌──────┐   role assign   ┌──────────┐   manual   ┌────────┐   manual   ┌──────────┐
│ Open │ ───────────────→│ Ordering │ ──────────→│ Placed │ ──────────→│ Received │
└──────┘                 └──────────┘            └────────┘            └──────────┘
    │                         │                      │                      │
    └─────────────────────────┴──────────────────────┴──────────────────────┘
                                                                            │
                                                                    ┌──────────┐
                                                                    │  Closed  │
                                                                    └──────────┘
```

| Transition | Declencheur | Action |
|-----------|-------------|--------|
| Open → Ordering | `AssignRole` | Auto quand runner/orderer est assigne |
| Ordering → Placed | `proposal.set_status` | Manuel par le runner/orderer |
| Placed → Received | `proposal.set_status` | Manuel par le runner/orderer |
| * → Closed | `CloseLunchSession` ou `proposal.close` | Toutes propositions fermees |

**Tests :**
- [ ] T3.2.1 : Creation de proposition → statut `Open`
- [ ] T3.2.2 : Attribution de role → statut `Ordering`
- [ ] T3.2.3 : Cloture de proposition → statut `Closed`
- [ ] T3.2.4 : Fermeture de session → toutes propositions `Closed`

### 3.3 QuickRunStatus : Machine a etats

```
┌──────┐   manual/auto   ┌────────┐   manual   ┌────────┐
│ Open │ ───────────────→│ Locked │ ──────────→│ Closed │
└──────┘                 └────────┘            └────────┘
    │                                               ↑
    └────────────── manual (close) ─────────────────┘
```

| Transition | Declencheur | Conditions |
|-----------|-------------|------------|
| Open → Locked | `LockQuickRun` ou `LockExpiredQuickRuns` | Runner (manuel) ou deadline expiree (auto) |
| Open → Closed | `CloseQuickRun` | Runner uniquement |
| Locked → Closed | `CloseQuickRun` | Runner uniquement |

**Tests :**
- [ ] T3.3.1 : Creation → statut `Open`
- [ ] T3.3.2 : Open → Locked par runner
- [ ] T3.3.3 : Open → Locked automatique (deadline)
- [ ] T3.3.4 : Open → Closed par runner
- [ ] T3.3.5 : Locked → Closed par runner
- [ ] T3.3.6 : Tentative de lock/close par non-runner → erreur
- [ ] T3.3.7 : Ajout de demande sur Quick Run Locked → refuse

---

## 4. Roles & Permissions

### 4.1 Matrice des roles

| Action | User normal | Runner/Orderer | Admin | Dev |
|--------|-------------|----------------|-------|-----|
| Creer proposition | ✓ (session open) | ✓ | ✓ | ✓ |
| Reclamer runner/orderer | ✓ | ✓ | ✓ | ✓ |
| Creer commande | ✓ (session open) | ✓ | ✓ (meme si locked) | ✓ |
| Modifier sa commande | ✓ (session open) | ✓ | ✓ | ✓ |
| Supprimer sa commande | ✓ (session open) | ✓ | ✓ | ✓ |
| Ajuster prix final | ✗ | ✓ (detenteur du role) | ✓ | ✓ |
| Cloturer proposition | ✗ | ✓ (detenteur du role) | ✓ | ✓ |
| Deleguer role | ✗ | ✓ (pour ceder) | ✓ | ✓ |
| Modifier quand session Locked | ✗ | ✗ | ✓ | ✓ |
| Creer/modifier restaurant | ✓ (si createur) | ✓ (si createur) | ✓ (tous) | ✓ |
| Reset base de donnees | ✗ | ✗ | ✗ | ✓ (env local) |
| Export vendors JSON | ✗ | ✗ | ✗ | ✓ (env local) |

### 4.2 Logique d'attribution des roles

| Type de livraison | Role auto-assigne au createur | Role a reclamer |
|---|---|---|
| Pickup (`FulfillmentType::Pickup`) | Runner | Orderer |
| Delivery (`FulfillmentType::Delivery`) | Orderer | Runner |

### 4.3 Verrouillage transactionnel

`AssignRole` et `DelegateRole` utilisent `DB::transaction()` + `lockForUpdate()` :

```php
DB::transaction(function () use ($proposal, $role, $userId) {
    $locked = VendorProposal::lockForUpdate()->find($proposal->id);
    // Verifie que le role n'est pas deja pris
    // Assigne si libre
});
```

**Tests :**
- [ ] T4.1 : Attribution runner sur Pickup → auto-assigne au createur
- [ ] T4.2 : Attribution orderer sur Delivery → auto-assigne au createur
- [ ] T4.3 : Deux users reclament runner simultanement → un seul reussit (race condition)
- [ ] T4.4 : Delegation runner → ancien libere, nouveau assigne
- [ ] T4.5 : Delegation par non-detenteur → erreur "Vous n'etes pas {role}"
- [ ] T4.6 : Admin peut cloturer n'importe quelle proposition
- [ ] T4.7 : User normal ne peut pas cloturer proposition d'un autre
- [ ] T4.8 : Admin peut modifier quand session locked
- [ ] T4.9 : User normal ne peut pas modifier quand session locked
- [ ] T4.10 : `canManageFinalPrices()` retourne true pour runner/orderer/admin

---

## 5. Dashboard : Etats & UI

### 5.1 DashboardState (6 etats)

| Etat | Code | Label | Condition de resolution |
|------|------|-------|------------------------|
| **NoProposal** | S1 | Aucune commande | Aucune proposition pour la session |
| **OpenProposalsNoOrder** | S2 | Commandes ouvertes | Propositions existent, user n'a pas commande |
| **HasOrder** | S3 | Ma commande | User a au moins une commande |
| **InCharge** | S4 | En charge | User est runner/orderer d'une proposition |
| **AllClosed** | S5 | Tout cloture | Toutes les propositions fermees |
| **History** | S6 | Historique | Session d'un jour passe |

### 5.2 Algorithme de resolution (`DashboardStateResolver`)

```
1. Si date != aujourd'hui → S6 (History)
2. Si toutes les propositions sont Closed → S5 (AllClosed)
3. Si user est runner/orderer d'une proposition non-fermee → S4 (InCharge)
4. Si user a une commande dans la session → S3 (HasOrder)
5. Si des propositions ouvertes existent → S2 (OpenProposalsNoOrder)
6. Sinon → S1 (NoProposal)
```

### 5.3 Transitions d'etat du Dashboard

| Etat actuel | Action utilisateur | Nouvel etat | Condition |
|-------------|-------------------|-------------|-----------|
| S1 | Cree une proposition + commande | S3 | Commande creee |
| S1 | Autre user cree une proposition | S2 | Propositions disponibles |
| S2 | Passe commande | S3 | Commande creee |
| S2 | Reclame runner/orderer | S4 | Devient responsable |
| S3 | Supprime sa commande | S2 (ou S1) | Plus de commandes |
| S3 | Reclame role sur autre proposition | S4 | Devient responsable |
| S4 | Delegue son role | S3 (ou S2) | Plus de responsabilite |
| S4/S3 | Deadline passe | Verrouillage session | Automatique |
| * | Toutes propositions fermees | S5 | Cloture |
| S5 | Clic "Relancer" | S2/S3/S4 | Nouvelle proposition |
| * | Jour suivant | S6 | Date passee |

**Tests :**
- [ ] T5.1 : Resolution S1 quand aucune proposition
- [ ] T5.2 : Resolution S2 quand propositions ouvertes sans commande
- [ ] T5.3 : Resolution S3 quand user a une commande
- [ ] T5.4 : Resolution S4 quand user est runner/orderer
- [ ] T5.5 : Resolution S5 quand toutes propositions fermees
- [ ] T5.6 : Resolution S6 pour une date passee
- [ ] T5.7 : S4 a priorite sur S3 (user est a la fois runner ET a une commande)
- [ ] T5.8 : Transition S1 → S3 apres creation proposition + commande
- [ ] T5.9 : Transition S3 → S2 apres suppression de sa commande
- [ ] T5.10 : `allowsActions()` retourne false pour S6 (History)

### 5.4 Elements UI par etat

| Etat | CTA principaux | Boutons visibles |
|------|---------------|------------------|
| S1 | "Demarrer une commande", "Proposer un nouveau restaurant" | `dashboard.start_from_catalog`, `dashboard.create_proposal` |
| S2 | Liste des propositions ouvertes avec "Commander ici" | `dashboard.join_proposal`, `proposal.take_charge` |
| S3 | Details de ma commande | `order.open_edit`, `order.delete` |
| S4 | Propositions gerees avec "Recap" / "Cloturer" | `proposal.open_recap`, `proposal.close` |
| S5 | "Relancer une commande" | `dashboard.relaunch` |
| S6 | Vue lecture seule | Aucun bouton d'action |

---

## 6. Flux de commande

### 6.1 Creation de commande

**Declencheur** : `order_create` (view_submission) via `OrderInteractionHandler::handleOrderCreate()`

**Champs du modal :**

| Champ | Block ID | Action ID | Type | Requis | Validation |
|-------|----------|-----------|------|--------|------------|
| Description | `description` | `description` | plain_text_input | Oui | Non vide |
| Prix estime | `price_estimated` | `price_estimated` | plain_text_input | Non | Format numerique (virgule/point) |
| Notes | `notes` | `notes` | plain_text_input (multiline) | Non | - |
| Prix final | `price_final` | `price_final` | plain_text_input | Non | Visible si runner/orderer ; format numerique |

**Logique :**
1. Si commande existante pour user + proposal → `UpdateOrder` (mise a jour)
2. Si nouvelle commande → `CreateOrder` (creation)
3. Si premiere commande de la proposition → post message "Nouvelle commande lancee" dans le thread

**Tests :**
- [ ] T6.1.1 : Creation d'une nouvelle commande avec description + prix
- [ ] T6.1.2 : Creation sans prix estime (optionnel)
- [ ] T6.1.3 : Description vide → erreur "Description requise."
- [ ] T6.1.4 : Prix invalide (lettres) → erreur "Prix estime invalide."
- [ ] T6.1.5 : Prix avec virgule "12,50" → converti en 12.50
- [ ] T6.1.6 : Commande existante → mise a jour au lieu de creation
- [ ] T6.1.7 : Premiere commande de la proposition → message thread poste
- [ ] T6.1.8 : Deuxieme commande → pas de message thread en double
- [ ] T6.1.9 : Session locked + user normal → "Les commandes sont verrouillees."
- [ ] T6.1.10 : Session locked + admin → commande acceptee

### 6.2 Edition de commande

**Declencheur** : `order_edit` (view_submission) via `OrderInteractionHandler::handleOrderEdit()`

**Logique :**
1. Verifie que la session n'est pas `Closed`
2. Si session Locked et user n'est pas runner/orderer → "Les commandes sont verrouillees."
3. `UpdateOrder` avec audit log
4. Met a jour le message de la proposition

**Tests :**
- [ ] T6.2.1 : Edition description + prix
- [ ] T6.2.2 : Edition sur session Closed → "La journee est cloturee."
- [ ] T6.2.3 : Edition sur session Locked par user normal → "Les commandes sont verrouillees."
- [ ] T6.2.4 : Edition sur session Locked par runner/orderer → autorise (prix final)
- [ ] T6.2.5 : Commande inexistante → response vide
- [ ] T6.2.6 : Message de proposition mis a jour apres edition

### 6.3 Suppression de commande

**Declencheur** : `order.delete` (block_action) via `OrderInteractionHandler::deleteUserOrder()`

**Logique :**
1. Verifie que l'utilisateur est le proprietaire (`DeleteOrder` verifie `provider_user_id`)
2. Supprime la commande
3. Met a jour le message de la proposition
4. Envoie "Commande supprimee." en ephemeral

**Tests :**
- [ ] T6.3.1 : Suppression de sa propre commande → succes
- [ ] T6.3.2 : Suppression d'une commande d'un autre → erreur
- [ ] T6.3.3 : Suppression met a jour le compteur de commandes sur la proposition
- [ ] T6.3.4 : Message ephemeral "Commande supprimee." envoye

### 6.4 Ajustement de prix final

**Declencheur** : `order_adjust_price` (view_submission) via `OrderInteractionHandler::handleAdjustPrice()`

**Logique :**
1. Verifie que la session n'est pas Closed
2. Verifie que l'utilisateur est runner/orderer ou admin (`canManageFinalPrices`)
3. Parse le prix final
4. Met a jour la commande

**Tests :**
- [ ] T6.4.1 : Runner ajuste le prix final → succes
- [ ] T6.4.2 : User normal tente d'ajuster → silencieusement refuse
- [ ] T6.4.3 : Prix final invalide → erreur "Prix final invalide."
- [ ] T6.4.4 : Commande d'une autre proposition → refuse
- [ ] T6.4.5 : Session Closed → refuse

---

## 7. Flux de proposition

### 7.1 Proposition avec vendor existant

**Declencheur** : `proposal_create` (view_submission) via `ProposalInteractionHandler::handleProposalSubmission()`

**Champs :**

| Champ | Block ID | Action ID | Type | Requis |
|-------|----------|-----------|------|--------|
| Enseigne | `enseigne` | `enseigne_id` | static_select | Oui |
| Mode de livraison | `fulfillment` | `fulfillment_type` | static_select | Oui (defaut: Pickup) |
| Deadline | `deadline` | `deadline_time` | plain_text_input | Non (defaut: 11:30) |
| Note | `note` | `note` | plain_text_input | Non |
| Aide demandee | `help` | `help_requested` | checkbox | Non |

**Logique :**
1. Session doit etre Open
2. Vendor doit etre actif
3. `ProposeVendor` cree VendorProposal + auto-assigne role
4. Modal transite vers le modal de commande (view_update)

**Tests :**
- [ ] T7.1.1 : Proposition avec vendor existant → VendorProposal cree
- [ ] T7.1.2 : Pickup → runner auto-assigne au createur
- [ ] T7.1.3 : Delivery → orderer auto-assigne au createur
- [ ] T7.1.4 : Vendor inactif → erreur "Enseigne invalide."
- [ ] T7.1.5 : Fulfillment invalide → erreur "Type invalide."
- [ ] T7.1.6 : Session Locked → "Les commandes sont verrouillees."
- [ ] T7.1.7 : Vendor deja propose dans la session → erreur doublon
- [ ] T7.1.8 : Transition modale vers le formulaire de commande

### 7.2 Proposition avec nouveau restaurant

**Declencheur** : `restaurant_propose` (view_submission) via `ProposalInteractionHandler::handleRestaurantPropose()`

**Champs supplementaires :**

| Champ | Block ID | Action ID | Type | Requis |
|-------|----------|-----------|------|--------|
| Nom | `name` | `name` | plain_text_input | Oui |
| URL Site | `url_website` | `url_website` | url_text_input | Non |
| Types de livraison | `fulfillment_types` | `fulfillment_types` | checkboxes | Oui (min 1) |
| Commande individuelle | `allow_individual` | `allow_individual_order` | checkbox | Non |
| Fichier | `file` | `file_upload` | file_input | Non |

**Logique :**
1. Cree le Vendor (`ProposeRestaurant` → `CreateVendor` + `ProposeVendor`)
2. Si fichier uploade : determine type (image → logo, document → menu)
3. Modal transite vers le formulaire de commande

**Tests :**
- [ ] T7.2.1 : Nom vide → erreur "Nom du restaurant requis."
- [ ] T7.2.2 : Aucun type de livraison → erreur "Au moins un type doit etre selectionne."
- [ ] T7.2.3 : Creation Vendor + VendorProposal en une seule action
- [ ] T7.2.4 : Upload image → collection `logo`
- [ ] T7.2.5 : Upload PDF → collection `menu`
- [ ] T7.2.6 : Transition modale vers le formulaire de commande

### 7.3 Delegation de role

**Declencheur** : `role_delegate` (view_submission) via `ProposalInteractionHandler::handleRoleDelegate()`

**Tests :**
- [ ] T7.3.1 : Runner delegue a un autre user → succes
- [ ] T7.3.2 : Non-detenteur tente de deleguer → "Vous n'etes pas {role}."
- [ ] T7.3.3 : Delegation met a jour le message de proposition
- [ ] T7.3.4 : Message de delegation poste dans le channel

### 7.4 Cloture de proposition

**Declencheur** : `proposal.close` (block_action) via `ProposalInteractionHandler::closeProposal()`

**Tests :**
- [ ] T7.4.1 : Runner ferme la proposition → statut `Closed`
- [ ] T7.4.2 : Non-responsable tente de fermer → "Seul le responsable peut cloturer."
- [ ] T7.4.3 : Admin peut fermer n'importe quelle proposition
- [ ] T7.4.4 : Message de proposition mis a jour avec statut ferme

---

## 8. Gestion des prix

### 8.1 Champs de prix

| Champ | Type | Qui le remplit | Quand |
|-------|------|---------------|-------|
| `price_estimated` | `decimal(8,2)` | User (createur commande) | A la creation/edition |
| `price_final` | `decimal(8,2) nullable` | Runner/Orderer | Apres placement commande |

### 8.2 Validation des prix

| Format | Resultat | Comportement |
|--------|----------|-------------|
| `12.50` | `12.50` | Accepte tel quel |
| `12,50` | `12.50` | Virgule convertie en point |
| `12` | `12.00` | Entier accepte |
| `abc` | `null` | Erreur "Prix estime/final invalide." |
| `` (vide) | `null` | Accepte (optionnel) |
| `0` | `0.00` | Accepte |
| `-5` | Selon implementation | A valider |

### 8.3 Calculs de totaux (recap)

```php
$estimated = $orders->sum('price_estimated');
$final = $orders->sum(fn ($o) => $o->price_final ?? $o->price_estimated);
```

**Tests :**
- [ ] T8.1 : Prix "12,50" converti en 12.50
- [ ] T8.2 : Prix "abc" retourne erreur
- [ ] T8.3 : Prix vide accepte
- [ ] T8.4 : Total estime = somme des prix estimes
- [ ] T8.5 : Total final = somme (prix_final ?? prix_estime)
- [ ] T8.6 : Prix final ecrase le prix estime dans le calcul final

---

## 9. Quick Run

### 9.1 Cycle de vie

```
Utilisateur clic "Quick Run" → Modal creation
    → Destination + delai (1-120 min) + note
    → Message poste dans le channel
    → Autres users ajoutent des demandes
    → Runner verrouille OU auto-lock a la deadline
    → Runner ferme avec ajustements de prix optionnels
    → Message de recap poste
```

### 9.2 Tests Quick Run

- [ ] T9.1 : Creation avec destination + delai valide
- [ ] T9.2 : Delai < 1 → erreur "Le delai doit etre entre 1 et 120 minutes."
- [ ] T9.3 : Delai > 120 → meme erreur
- [ ] T9.4 : Destination vide → erreur "Destination requise."
- [ ] T9.5 : Ajout de demande avec description + prix
- [ ] T9.6 : Description demande vide → erreur
- [ ] T9.7 : Edition de demande existante
- [ ] T9.8 : Suppression de demande par le createur → succes
- [ ] T9.9 : Suppression de demande par un autre user → erreur
- [ ] T9.10 : Lock par le runner → succes + message
- [ ] T9.11 : Lock par non-runner → erreur
- [ ] T9.12 : Ajout demande sur Quick Run locked → "Ce Quick Run n'accepte plus de demandes."
- [ ] T9.13 : Close par runner → succes + recap
- [ ] T9.14 : Close par non-runner → "Seul le runner peut cloturer ce Quick Run."
- [ ] T9.15 : Close avec ajustement de prix
- [ ] T9.16 : Auto-lock a la deadline via `LockExpiredQuickRuns`
- [ ] T9.17 : Recap affiche totaux estimes et finaux

### 9.3 Differences Quick Run vs Session

| Aspect | LunchSession | QuickRun |
|--------|-------------|----------|
| Vendor requis | Oui | Non (destination libre) |
| Deadline | Heure fixe | Delai relatif (1-120 min) |
| Roles | Runner + Orderer | Runner uniquement (createur) |
| Propositions multiples | Oui | Non |
| Duree | Journee | Minutes/heures |

---

## 10. Gestion des restaurants

### 10.1 Creation de vendor

**Via** : `enseigne_create` (view_submission) ou integre dans `restaurant_propose`

**Attributs :**

| Attribut | Type | Requis | Validation |
|----------|------|--------|------------|
| `name` | string | Oui | Non vide |
| `cuisine_type` | string | Non | - |
| `fulfillment_types` | array | Oui | Min 1 element |
| `allow_individual_order` | boolean | Non | Default false |
| `url_website` | string | Non | - |
| `url_menu` | string | Non | - |
| `active` | boolean | Oui | Default true |
| `emoji_name` | string | Non | Emoji Slack custom |
| `notes` | text | Non | - |
| `created_by_provider_user_id` | string | Oui | Auto (createur) |

### 10.2 Tests Vendor

- [ ] T10.1 : Creation vendor avec tous les champs
- [ ] T10.2 : Creation vendor avec champs minimaux (nom + types)
- [ ] T10.3 : Edition vendor par le createur → autorise
- [ ] T10.4 : Edition vendor par un autre user → refuse
- [ ] T10.5 : Edition vendor par admin → autorise
- [ ] T10.6 : Desactivation de vendor (`active = false`)
- [ ] T10.7 : Vendor desactive n'apparait pas dans le catalogue
- [ ] T10.8 : Upload logo (image) → collection `logo` + thumbnail 128x128
- [ ] T10.9 : Upload menu (PDF) → collection `menu`
- [ ] T10.10 : Recherche vendor par nom dans la liste
- [ ] T10.11 : Logique emoji : `emoji_name` > `cuisine_type` mapping > default

---

## 11. Taches planifiees

### 11.1 Schedule (routes/console.php)

| Tache | Frequence | Action | Condition |
|-------|-----------|--------|-----------|
| Lock sessions expirees | Chaque minute | `LockExpiredSessions` | Session Open + `deadline_at ≤ now()` |
| Lock Quick Runs expires | Chaque minute | `LockExpiredQuickRuns` | QuickRun Open + `deadline_at ≤ now()` |
| Kickoff quotidien | Desactive (commente) | `CreateLunchSession` + post | - |

### 11.2 Tests Schedule

- [ ] T11.1 : `LockExpiredSessions` verrouille les sessions Open avec deadline depassee
- [ ] T11.2 : `LockExpiredSessions` ne touche pas les sessions deja Locked/Closed
- [ ] T11.3 : `LockExpiredSessions` ne touche pas les sessions avec deadline future
- [ ] T11.4 : `LockExpiredQuickRuns` verrouille les Quick Runs Open avec deadline depassee
- [ ] T11.5 : `LockExpiredQuickRuns` ne touche pas les Quick Runs deja Locked/Closed
- [ ] T11.6 : Notification envoyee apres verrouillage

---

## 12. Multi-tenant

### 12.1 Pattern d'isolation

L'application utilise un pattern `organization_id` pour l'isolation des donnees :
- Middleware `slack.organization` resout l'organisation depuis le payload Slack
- `OrganizationScope` (global scope) filtre automatiquement les queries
- `BelongsToOrganization` trait ajoute le scope + auto-set `organization_id`

### 12.2 Tests Multi-tenant

- [ ] T12.1 : Sessions d'une org ne sont pas visibles pour une autre org
- [ ] T12.2 : Vendors scopes par organisation
- [ ] T12.3 : Quick Runs scopes par organisation
- [ ] T12.4 : Le global scope `OrganizationScope` filtre correctement
- [ ] T12.5 : Le middleware `ResolveOrganization` resout depuis le payload Slack
- [ ] T12.6 : Pas de fuite de donnees cross-organisation dans les dashboards

---

## 13. Cas limites & erreurs

### 13.1 Violations d'etat de session

| Scenario | Message d'erreur | Code |
|----------|-----------------|------|
| Commande apres deadline (user normal) | "Les commandes sont verrouillees." | Ephemeral |
| Proposition apres deadline | "Les commandes sont verrouillees." | Ephemeral |
| Commande sur session Closed | "La journee est cloturee." | Ephemeral |
| Close session deja Closed | Idempotent (pas d'erreur) | 200 |
| Admin modifie session Locked | Autorise | 200 |

### 13.2 Violations de role

| Scenario | Message d'erreur |
|----------|-----------------|
| Non-runner tente lock Quick Run | "Seul le runner peut verrouiller" |
| Non-responsable tente ajuster prix | Silencieusement refuse |
| Non-detenteur tente deleguer | "Vous n'etes pas {role}." |
| Role deja assigne | "Role deja attribue." ou "Un responsable est deja assigne." |
| Non-responsable tente cloturer proposition | "Seul le responsable peut cloturer." |
| Non-responsable tente voir recap | "Seul le responsable peut voir le recapitulatif." |

### 13.3 Erreurs de validation

| Champ | Message d'erreur | Declencheur |
|-------|-----------------|-------------|
| `description` (order) | "Description requise." | Vide |
| `price_estimated` | "Prix estime invalide." | Non numerique |
| `price_final` | "Prix final invalide." | Non numerique |
| `name` (vendor) | "Nom du restaurant requis." | Vide |
| `fulfillment_types` (vendor) | "Au moins un type doit etre selectionne." | Aucun coche |
| `destination` (Quick Run) | "Destination requise." | Vide |
| `delay_minutes` (Quick Run) | "Le delai doit etre entre 1 et 120 minutes." | < 1 ou > 120 |
| `enseigne_id` | "Enseigne invalide." | Vendor inactif ou inexistant |
| `fulfillment_type` | "Type invalide." | Valeur hors enum |

### 13.4 Prevention des doublons

| Entite | Regle | Mecanisme |
|--------|-------|-----------|
| Vendor dans session | Un seul proposal par vendor par session | Check dans `ProposeVendor` |
| Commande par user/proposal | Une seule commande par user par proposal | Create OR Update (idempotent) |
| Role par proposal | Un seul runner, un seul orderer | `lockForUpdate()` transactionnel |

### 13.5 Gestion des erreurs globales (view_submission)

```php
// InvalidArgumentException → erreur business
→ response JSON avec errorModal

// Throwable → erreur technique
→ response JSON avec errorModal generique "Une erreur est survenue."
```

**Tests :**
- [ ] T13.1 : `InvalidArgumentException` dans view_submission → modal d'erreur business
- [ ] T13.2 : Exception generique → modal d'erreur generique
- [ ] T13.3 : Payload Slack avec action_id inconnu → ignore (pas de crash)
- [ ] T13.4 : Payload Slack avec callback_id inconnu → response vide
- [ ] T13.5 : Proposal inexistant dans metadata → response vide (pas de crash)
- [ ] T13.6 : Order inexistant → response vide

---

## 14. Scenarios end-to-end

### 14.1 Happy Path : Cycle complet d'une commande groupe

```
1. User A ouvre le dashboard → etat S1 (aucune proposition)
2. User A clic "Demarrer une commande" → modal catalogue
3. User A selectionne Pizza Place + Pickup → proposal_create
4. VendorProposal creee, runner = User A → modal ordre
5. User A remplit "Margherita 12€" → order_create
6. Message "Nouvelle commande lancee" poste en thread
7. User B ouvre le dashboard → etat S2 (proposition ouverte)
8. User B clic "Commander ici" → modal ordre
9. User B remplit "Calzone 14€" → order_create
10. User A clic "Voir recapitulatif" → recap (2 commandes, total 26€)
11. User A clic "Ajuster prix final" → modal ajustement
12. User A ajuste Margherita → 11€, Calzone → 13.50€
13. User A clic "Cloturer" → proposal Closed
14. Dashboard montre S5 (tout cloture)
```

### 14.2 Happy Path : Quick Run

```
1. User A clic "Quick Run" → modal creation
2. User A saisit "Boulangerie du coin" + 30 min
3. Quick Run cree + message poste
4. User B clic "Ajouter une demande" → modal demande
5. User B saisit "Pain aux cereales 3€"
6. User C ajoute "Croissants x2 4€"
7. Apres 30 min → auto-lock par scheduler
8. User A clic "Ajuster les prix"
9. User A ajuste les prix finaux
10. User A clic "Cloturer" → recap poste
```

### 14.3 Edge Case : Race condition sur l'attribution de role

```
1. Proposition creee sans runner (ou orderer selon fulfillment)
2. User A et User B cliquent "Je suis runner" au meme moment
3. AssignRole utilise lockForUpdate() en transaction
4. Un seul reussit → role assigne
5. L'autre recoit "Role deja attribue." / "Un responsable est deja assigne."
```

### 14.4 Edge Case : Verrouillage a la deadline

```
1. Session creee avec deadline 11:30
2. Scheduler tourne a 11:31 → LockExpiredSessions
3. Session passe en Locked
4. User normal tente de commander → "Les commandes sont verrouillees."
5. Admin tente de commander → succes (bypass)
```

### 14.5 Edge Case : Deduplication de vendor

```
1. User A propose Pizza Place pour la session du jour
2. User B tente aussi de proposer Pizza Place
3. ProposeVendor detecte le doublon → erreur
```

### 14.6 Edge Case : Proposition sans vendors dans le catalogue

```
1. Aucun vendor actif dans l'organisation
2. User clic "Demarrer une commande"
3. startFromCatalog() detecte vendors vide
4. Fallback → ouvre proposeRestaurantModal (nouveau restaurant)
```

### 14.7 Edge Case : Commande existante → mise a jour

```
1. User A passe commande "Margherita" sur Pizza Place
2. User A re-ouvre le modal de commande pour Pizza Place
3. Modal pre-rempli avec "Margherita"
4. User modifie → "Quatre Fromages"
5. order_create detecte commande existante → UpdateOrder
```

### 14.8 Edge Case : Session fermee → propositions fermees

```
1. Session avec 3 propositions (Open, Ordering, Placed)
2. Runner/admin ferme la session
3. CloseLunchSession ferme toutes les propositions → Closed
4. Aucune action possible sur les propositions
```

---

## 15. Couverture existante vs. manquante

### 15.1 Tests existants (bien couverts)

| Categorie | Fichiers | Statut |
|-----------|----------|--------|
| **Actions LunchSession** | `CreateLunchSessionTest`, `CloseLunchSessionTest`, `LockExpiredSessionsTest` | ✅ Complet |
| **Actions Order** | `CreateOrderTest`, `UpdateOrderTest`, `DeleteOrderTest` | ✅ Complet |
| **Actions QuickRun** | `CreateQuickRunTest`, `AddQuickRunRequestTest`, `UpdateQuickRunRequestTest`, `DeleteQuickRunRequestTest`, `LockQuickRunTest`, `LockExpiredQuickRunsTest`, `CloseQuickRunTest` | ✅ Complet |
| **Actions Vendor** | `CreateVendorTest`, `UpdateVendorTest` | ✅ Complet |
| **Actions VendorProposal** | `AssignRoleTest`, `DelegateRoleTest`, `ProposeVendorTest`, `ProposeRestaurantTest` | ✅ Complet |
| **Models** | `LunchSessionTest`, `OrderTest`, `VendorTest`, `VendorProposalTest`, `OrganizationTest` | ✅ Complet |
| **Policies** | `OrderPolicyTest`, `VendorPolicyTest`, `VendorProposalPolicyTest` | ✅ Complet |
| **Enums** | `DashboardStateTest`, `SlackActionTest`, `OrderingModeTest` | ✅ Complet |
| **Middleware** | `VerifySlackSignatureTest`, `ResolveOrganizationTest`, `LogRequestMiddlewareTest` | ✅ Complet |
| **Multi-tenant** | `MultiTenancyIsolationTest`, `OrganizationScopeTest`, `BelongsToOrganizationTest` | ✅ Complet |
| **Services Slack** | `SlackServiceTest`, `SlackMessengerTest`, `SlackBlockBuilderTest`, `DashboardBlockBuilderTest`, `DashboardStateResolverTest`, `DashboardContextTest` | ✅ Complet |
| **Workflow** | `LunchSessionWorkflowTest` | ⚠️ Basique |

### 15.2 Tests manquants (a creer)

| Categorie | Priorite | Description | Fichier suggere |
|-----------|----------|-------------|-----------------|
| **SlackInteractionHandler dispatch** | P1 | Verifier le routage de chaque action_id vers le bon handler | `tests/Feature/Services/Slack/SlackInteractionHandlerDispatchTest.php` |
| **OrderInteractionHandler** | P1 | handleOrderCreate, handleOrderEdit, handleAdjustPrice, deleteUserOrder | `tests/Feature/Services/Slack/Handlers/OrderInteractionHandlerTest.php` |
| **ProposalInteractionHandler** | P1 | handleProposalSubmission, handleRestaurantPropose, handleRoleDelegate, closeProposal, claimRole, takeCharge | `tests/Feature/Services/Slack/Handlers/ProposalInteractionHandlerTest.php` |
| **SessionInteractionHandler** | P1 | handleLunchDashboard, closeSession, canCloseSession | `tests/Feature/Services/Slack/Handlers/SessionInteractionHandlerTest.php` |
| **QuickRunInteractionHandler** | P1 | handleQuickRunCreate, handleRequestCreate, handleRequestEdit, handleClose, handleLock | `tests/Feature/Services/Slack/Handlers/QuickRunInteractionHandlerTest.php` |
| **VendorInteractionHandler** | P2 | handleVendorCreate, handleVendorUpdate, vendor search, dev actions | `tests/Feature/Services/Slack/Handlers/VendorInteractionHandlerTest.php` |
| **Endpoint interactivity complet** | P2 | POST `/api/slack/interactivity` avec payloads Slack signes, verification du routage block_actions et view_submission | `tests/Feature/Http/Controllers/SlackInteractivityTest.php` |
| **Dashboard state transitions** | P2 | Transitions d'etat S1→S3, S3→S2, etc. dans des scenarios multi-actions | `tests/Feature/DashboardStateTransitionTest.php` |
| **Scenarios end-to-end** | P3 | Cycles complets (creation session → proposition → commande → cloture) | `tests/Feature/Workflows/FullOrderLifecycleTest.php` |
| **Quick Run end-to-end** | P3 | Cycle complet Quick Run | `tests/Feature/Workflows/QuickRunLifecycleTest.php` |

### 15.3 Plan de mise en oeuvre

**Phase 1 (P1) — Handlers Slack :**
Creer les tests pour les 5 interaction handlers. Chaque test doit :
1. Mocker `SlackService` et `SlackMessenger` (pas d'appels HTTP reels)
2. Creer les modeles via factories (session, proposal, order, vendor)
3. Simuler les payloads Slack (block_actions, view_submission)
4. Verifier les effets de bord (models crees/modifies, methodes messenger appelees)

**Phase 2 (P2) — Integration :**
Tester l'endpoint `/api/slack/interactivity` avec des payloads signes.
Tester les transitions d'etat du dashboard dans des scenarios multi-etapes.

**Phase 3 (P3) — End-to-End :**
Scenarios complets simulant un cycle de vie entier depuis la creation de session jusqu'a la cloture.

---

## Annexe : Correspondance SlackAction → Categorisation

```php
isSession()   → OpenLunchDashboard, SessionClose, CloseDay, DashboardCloseSession
isOrder()     → OpenOrderForProposal, OrderOpenEdit, OrderDelete, DashboardJoinProposal,
                DashboardOrderHere, DashboardMyOrder, OpenOrderModal, OpenEditOrderModal
isDev()       → DevResetDatabase, DevExportVendors
isVendor()    → OpenAddEnseigneModal, OpenManageEnseigneModal, DashboardVendorsList,
                VendorsListSearch, VendorsListEdit
isQuickRun()  → QuickRunOpen, QuickRunAddRequest, QuickRunEditRequest, QuickRunDeleteRequest,
                QuickRunLock, QuickRunClose, QuickRunRecap, QuickRunAdjustPrices
isProposal()  → OpenProposalModal, DashboardStartFromCatalog, DashboardRelaunch,
                DashboardCreateProposal, DashboardChooseFavorite, DashboardProposeVendor,
                ProposalOpenManage, ProposalTakeCharge, ProposalOpenRecap, ProposalClose,
                ProposalSetStatus, ClaimRunner, ClaimOrderer, OpenDelegateModal,
                OpenAdjustPriceModal, OpenSummary, DashboardClaimResponsible, DashboardViewOrders
isCallback()  → proposal_*, order_*, lunch_*, enseigne_*, restaurant_*, role_*, quickrun_*
```
