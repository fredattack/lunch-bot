# Code Review - Changements Post-Audit

**Date** : 15 fevrier 2026
**Ref audit** : `AUDIT.md` (commit `f3d40e5`)
**Scope** : 20 fichiers modifies, ~1901 lignes ajoutees, ~1605 supprimees
**Tests** : 476 tests, 1038 assertions, 0 echecs (9.26s)

---

## Verdict

| Dimension | Score | Commentaire |
|-----------|-------|-------------|
| Couverture de l'audit | 11/14 | Bonne couverture des items identifies |
| Qualite du refactoring | 8/10 | Decomposition propre, quelques points a corriger |
| Nouveaux tests | 9/10 | +196 tests, gaps critiques combles |
| Regressions introduites | 2 | Voir section bugs |
| Items audit non traites | 3 | Voir section lacunes |

**Le changeset est globalement solide. 3 problemes doivent etre resolus avant merge.**

---

## 1. PROBLEMES A CORRIGER AVANT MERGE

### 1.1 CRITIQUE : DevResetDatabase toujours executable en production

**Fichier** : `VendorInteractionHandler.php:246-254`

L'audit (1.1) recommandait explicitement un guard `app()->environment('local')`. Le changement deplace le user ID vers `config('slack.dev_user_id')` mais ne protege PAS contre l'execution en production. Si `SLACK_DEV_USER_ID` est defini en production (oubli de nettoyage `.env`, copie depuis l'environnement de dev), `migrate:fresh --force` reste accessible.

```php
private function devResetDatabase(string $userId, string $channelId): void
{
    if (! $this->isDevUser($userId)) {
        return;
    }
    // Aucun check d'environnement ici
    Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
}
```

**Correction requise** :

```php
private function devResetDatabase(string $userId, string $channelId): void
{
    if (! app()->environment('local', 'testing') || ! $this->isDevUser($userId)) {
        return;
    }

    Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    $this->messenger->postEphemeral($channelId, $userId, 'Base de donnees reinitialisee avec succes.');
}
```

Meme chose pour `devExportVendors` : potentiellement sensible en prod.

### 1.2 BUG : ExtractsSlackTeamId perd le parsing raw content

**Fichier** : `ExtractsSlackTeamId.php`

L'ancien `VerifySlackSignature::extractTeamId()` parsait le body brut via `$request->getContent()` + `json_decode()`. Le trait utilise `$request->all()` qui depend du middleware de parsing Laravel. Or, `VerifySlackSignature` s'execute AVANT le parsing du body dans la pipeline middleware.

Pour les requetes `application/json` (events API), `$request->all()` fonctionne car Laravel parse le JSON automatiquement. Mais le contrat est different : `getContent()` est garanti de fonctionner quel que soit l'ordre des middlewares, `all()` ne l'est pas.

**Risque** : Si le signing secret est multi-tenant (resolu par team ID), le middleware pourrait ne pas trouver le team ID et tomber sur le secret par defaut.

**Correction recommandee** : Ajouter un fallback vers `json_decode($request->getContent())` dans le trait quand `$request->all()` ne retourne pas de team_id.

### 1.3 BUG : Scheduler LockExpiredSessions hors contexte multi-tenant

**Fichier** : `routes/console.php:35-43`

```php
Schedule::call(function () {
    $lockAction = app(LockExpiredSessions::class);
    $messenger = app(SlackMessenger::class);

    $lockedSessions = $lockAction->handle();

    if ($lockedSessions->isNotEmpty()) {
        $messenger->notifySessionsLocked($lockedSessions);
    }
})->everyMinute();
```

`LockExpiredSessions` utilise probablement le global scope de l'organization. Sans `Organization::setCurrent()`, soit le scope est ignore (toutes les sessions sont traitees, OK), soit le scope filtre et rien n'est locke (KO).

De plus, `SlackMessenger::notifySessionsLocked()` a besoin du bon token bot par organisation. Sans contexte tenant, le token par defaut sera utilise pour toutes les organisations.

**Correction** : Iterer les organisations comme dans le premier scheduler :

```php
Schedule::call(function () {
    $lockAction = app(LockExpiredSessions::class);
    $messenger = app(SlackMessenger::class);

    Organization::with('installation')
        ->whereHas('installation')
        ->each(function (Organization $organization) use ($lockAction, $messenger) {
            Organization::setCurrent($organization);
            $lockedSessions = $lockAction->handle();
            if ($lockedSessions->isNotEmpty()) {
                $messenger->notifySessionsLocked($lockedSessions);
            }
        });
})->everyMinute();
```

---

## 2. PROBLEMES MINEURS (a corriger post-merge)

### 2.1 processFileUpload duplique entre handlers

`ProposalInteractionHandler.php:192-237` et `VendorInteractionHandler.php:119-148` contiennent la meme methode `processFileUpload()` avec des variantes mineures (le premier a plus de logging). Ironiquement, le refactoring qui visait a reduire la duplication en a introduit une nouvelle.

**Solution** : Deplacer dans `BaseInteractionHandler` ou creer un `FileUploadHandler` dedie.

### 2.2 ProposalInteractionHandler depend de OrderInteractionHandler

`ProposalInteractionHandler` injecte `OrderInteractionHandler` pour appeler `canManageFinalPrices()` (lignes 31, 396, 420, 473, 497). Cela cree un couplage inter-handlers qui va a l'encontre du pattern de decomposition par domaine.

**Solution** : Extraire `canManageFinalPrices()` dans `BaseInteractionHandler` ou un trait `AuthorizesProposalActions`.

### 2.3 SlackAction::isVendor() inclut les actions Dev

```php
public function isVendor(): bool
{
    return in_array($this, [
        // ...
        self::DevResetDatabase,   // Pas une action vendor
        self::DevExportVendors,   // Pas une action vendor
    ], true);
}
```

Semantiquement faux. `DevResetDatabase` n'est pas une action vendor. Cela polluera le routing si un handler vendor ajoute de la logique commune. Creer une methode `isDev()` separee.

### 2.4 Routing par fallthrough dans handleBlockActions

```php
if ($slackAction->isSession()) { ... return; }
if ($slackAction->isOrder())   { ... return; }
if ($slackAction->isVendor())  { ... return; }
$this->proposalHandler->handleBlockAction(...); // fallthrough
```

`ProposalInteractionHandler` est le handler par defaut pour toute action non categorisee. Toute nouvelle action non enregistree dans `isSession/isOrder/isVendor` sera silencieusement routee vers le proposal handler au lieu d'etre ignoree. Cela peut provoquer des bugs subtils.

**Solution** : Ajouter `isProposal()` dans l'enum et un guard explicite :

```php
if ($slackAction->isProposal()) {
    $this->proposalHandler->handleBlockAction(...);
    return;
}
Log::warning('Unhandled block action', ['action_id' => $actionId]);
```

### 2.5 Scheduler ne reset pas le contexte tenant

`routes/console.php:12-30` - La boucle `Organization::each()` appelle `setCurrent()` mais ne reset jamais a `null` apres. Si une exception est levee mid-loop ou si du code s'execute apres le scheduler, le contexte du dernier tenant traite persiste.

**Correction** : Ajouter `Organization::setCurrent(null)` dans un `finally` ou apres la boucle.

### 2.6 isDuplicateRetry renomme mais pas corrige

**Fichier** : `SlackController.php:62-65`

L'audit (2.4) identifiait que les retries Slack sont rejetes aveuglement. Le code a ete refactorise (extraction de `isDuplicateRetry()`) mais le comportement reste identique : tout retry est ignore, meme si le premier traitement a echoue.

Le renommage en `isDuplicateRetry` est meme trompeur car la methode ne verifie aucune duplication effective.

### 2.7 VendorInteractionHandler importe FQCN inline

`VendorInteractionHandler.php:174` utilise `\App\Models\VendorProposal::with('vendor')` au lieu d'un import en tete de fichier. Inconsistant avec le reste du code.

`ProposalInteractionHandler.php:427` fait la meme chose avec `\App\Enums\ProposalStatus::Closed`.

---

## 3. AUDIT : ITEMS TRAITES

| Ref Audit | Item | Status | Commentaire |
|-----------|------|--------|-------------|
| 1.1 | DevResetDatabase | Partiel | Config OK, guard env manquant |
| 1.2 | Scheduler | OK | Implemente avec multi-tenant pour kickoff |
| 1.3 | Retry/Rate limiting Slack API | OK | retry(3, 1000) + timeout(10) |
| 2.1 | Race condition DelegateRole | OK | DB::transaction + lockForUpdate |
| 2.2 | AssignRole guard status | OK | `if ($locked->status === ProposalStatus::Open)` |
| 2.3 | Fuite fichiers temporaires | OK | try/finally + @unlink |
| 2.6 | Duplication extractTeamId | OK | Trait ExtractsSlackTeamId |
| 3.1 | God object InteractionHandler | OK | Decompose en 4 handlers + base |
| 3.2 | Code mort | OK | lunchDashboardModal, postProposalMessage, SlackActions supprimes |
| 3.2 | updateProposalMessage deprecated | OK | Tag @deprecated retire |
| 3.4 | Duplication block builders | OK | Trait SlackBlockHelpers |
| 3.5 | Prix inconsistant | OK | formatPrice() centralise |

## 4. AUDIT : ITEMS NON TRAITES

| Ref Audit | Item | Risque |
|-----------|------|--------|
| 2.4 | Retries Slack idempotents | MOYEN - Toujours rejetes aveuglement |
| 2.5 | Inconsistance DeleteOrder locked | MOYEN - Toujours permis en locked |
| 5.1 | Cache teamInfo() | MOYEN - Toujours appele a chaque dashboard |
| 5.2 | Side effect dans DashboardStateResolver | BAS |
| 5.3 | N+1 vendor media | BAS |
| 5.4 | Index composite lunch_sessions | BAS |
| 6.2 | Cache isAdmin() | MOYEN - Toujours appele a chaque action |

---

## 5. QUALITE DU REFACTORING

### Points forts

1. **Decomposition handler exemplaire** : 1283 lignes -> 124 lignes + 4 handlers specialises. Chaque handler a une responsabilite claire et un scope bien defini.

2. **BaseInteractionHandler bien concu** : Les methodes utilitaires partagees (`stateValue`, `parsePrice`, `decodeMetadata`, `ensureSessionOpen`, `buildActor`) sont correctement mutualisees.

3. **SlackBlockHelpers** : `button()`, `formatTime()`, `formatPrice()`, `fulfillmentLabel()` - elimine la duplication exacte entre builders. Le trait est minimaliste et cible.

4. **ExtractsSlackTeamId** propre : Resolution correcte de la duplication entre middlewares (sous reserve du point 1.2).

5. **SlackInteractionHandler est maintenant lisible** : Le dispatch par type/domaine via les methodes `isSession/isOrder/isVendor` est clair, meme si le fallthrough sur proposalHandler est a corriger.

6. **Conservation du comportement** : Aucune regression fonctionnelle detectee dans les 476 tests. Le refactoring est iso-fonctionnel.

### Points faibles

1. **Couplage ProposalHandler -> OrderHandler** : `canManageFinalPrices()` est une methode d'autorisation, pas de commande. Elle devrait etre dans un service ou trait partage.

2. **processFileUpload duplique** : Defait partiellement le travail de deduplication.

3. **Tests du handler utilisent le container Laravel** : Le `setUp()` du test fait `$this->app->make(SlackInteractionHandler::class)` ce qui est un test d'integration plus qu'un test unitaire. C'est acceptable mais plus fragile.

---

## 6. QUALITE DES TESTS AJOUTES

### Metriques

| Composant | Avant | Apres | Delta |
|-----------|-------|-------|-------|
| Tests totaux | 280 | 476 | +196 |
| Assertions | 537 | 1038 | +501 |
| Temps | 4.72s | 9.26s | +4.54s |

### Gaps combles (ref audit 4.2 et 4.3)

| Gap identifie | Comble ? |
|---------------|----------|
| SlackService (HTTP client) | OUI - 419 lignes de tests |
| SlackMessenger (orchestration) | OUI - 507 lignes de tests |
| SlackBlockBuilder | OUI - +645 lignes de tests |
| SlackInteractionHandler | OUI - +973 lignes de tests |
| AssignRole status regression | OUI - 2 tests (Placed, Received) |
| UpdateOrder prix extremes | OUI - test_handles_very_large_price_value |
| LockExpiredSessions DST | OUI - spring forward + fall back |
| CreateLunchSession deadline passee | OUI - test_handles_deadline_in_the_past |

### Points positifs des tests

- Tests DST bien penses avec `Carbon::setTestNow()` et les dates reelles de changement d'heure 2025
- Tests de non-regression pour AssignRole status (exactement le bug identifie en 2.2)
- Coverage multi-champ pour UpdateOrder
- Teardown Mockery propre avec `#[After]`

### Tests manquants

- Tests de concurrence pour AssignRole (2 users cliquent en meme temps) - pas teste
- Integration E2E workflow complet (session > proposal > order > close) - pas teste
- Test du scheduler (`routes/console.php`) - pas teste
- Test de VendorInteractionHandler.processFileUpload vs ProposalInteractionHandler.processFileUpload (differences non verifiees)

---

## 7. MODIFICATIONS FICHIER PAR FICHIER

### `app/Actions/VendorProposal/AssignRole.php`
- Guard status ajoute : ne passe a Ordering que si Open
- Correct, clos l'item 2.2

### `app/Actions/VendorProposal/DelegateRole.php`
- Transaction + lockForUpdate ajoutes
- Refresh apres succes
- Correct, clos l'item 2.1

### `app/Enums/SlackAction.php`
- Ajout de `isSession()`, `isOrder()`, `isVendor()` pour le routing
- DevResetDatabase/DevExportVendors mal categorises dans `isVendor()` (cf 2.3)

### `app/Http/Controllers/SlackController.php`
- Extraction de `isDuplicateRetry()`
- Ajout de `JSON_THROW_ON_ERROR` sur le parsing du payload interactivity
- Bonne amelioration : catch `\JsonException` + 400

### `app/Http/Middleware/ExtractsSlackTeamId.php`
- Nouveau trait mutualisant la logique
- Risque potentiel sur le parsing pre-middleware (cf 1.2)

### `app/Http/Middleware/ResolveOrganization.php` / `VerifySlackSignature.php`
- Remplacement de la methode dupliquee par `use ExtractsSlackTeamId`
- Propre

### `app/Services/Slack/DashboardBlockBuilder.php`
- Suppression de `DEV_USER_ID` hardcode -> `config('slack.dev_user_id')`
- Utilisation du trait `SlackBlockHelpers`
- Suppression de `button()` duplique
- Utilisation de `fulfillmentLabel()` et `formatPrice()` centralisees

### `app/Services/Slack/SlackActions.php`
- Supprime (code mort). Correctement remplace par l'enum `SlackAction`

### `app/Services/Slack/SlackBlockBuilder.php`
- Utilisation du trait `SlackBlockHelpers`
- Suppression de `button()`, `formatTime()`, `lunchDashboardModal()`, `dashboardProposalBlocks()`
- Centralisation `fulfillmentLabel()` et `formatPrice()`
- ~187 lignes supprimees, bon nettoyage

### `app/Services/Slack/SlackInteractionHandler.php`
- 1283 -> 124 lignes
- Delegation vers 4 handlers domaine
- View submission error responses migrees vers `response()->json(...)` direct

### `app/Services/Slack/SlackMessenger.php`
- Suppression de `postProposalMessage()` (deprecated + jamais appele)
- Suppression du tag `@deprecated` sur `updateProposalMessage()` (encore utilise)

### `app/Services/Slack/SlackService.php`
- `client()` : ajout `timeout(10)` + `retry(3, 1000, ...)` sur 429 et ConnectionException
- Correct et minimal

### `config/slack.php`
- Ajout `dev_user_id` via env var

### `routes/console.php`
- Scheduler implemente pour kickoff quotidien + lock expirees
- Kickoff itere les organisations multi-tenant
- Lock session manque le contexte tenant (cf 1.3)

### `app/Services/Slack/Handlers/BaseInteractionHandler.php`
- Classe abstraite bien concue avec les utilitaires partages
- 141 lignes, responsabilite claire

### `app/Services/Slack/Handlers/OrderInteractionHandler.php`
- 368 lignes, gestion propre des commandes
- `canManageFinalPrices()` rendue `public` pour etre appelee depuis ProposalHandler (design discutable)

### `app/Services/Slack/Handlers/ProposalInteractionHandler.php`
- 531 lignes, la plus grosse des handlers
- Injection de OrderInteractionHandler (couplage, cf 2.2)
- processFileUpload duplique (cf 2.1)

### `app/Services/Slack/Handlers/SessionInteractionHandler.php`
- 96 lignes, compact et clair
- `canCloseSession()` correctement place ici

### `app/Services/Slack/Handlers/VendorInteractionHandler.php`
- 286 lignes
- processFileUpload duplique (cf 2.1)
- FQCN inline (cf 2.7)

---

## 8. RESUME DES ACTIONS

### Avant merge (3 items)

1. Ajouter `app()->environment('local', 'testing')` guard sur `devResetDatabase` et `devExportVendors`
2. Verifier le comportement d'ExtractsSlackTeamId dans VerifySlackSignature (pre-body-parse)
3. Ajouter le contexte multi-tenant dans le scheduler LockExpiredSessions

### Post-merge rapide (5 items)

4. Extraire `canManageFinalPrices()` dans BaseInteractionHandler
5. Dedupliquer `processFileUpload()` dans BaseInteractionHandler
6. Creer `isDev()` dans SlackAction et retirer les dev actions de `isVendor()`
7. Ajouter un guard explicite pour le fallthrough vers ProposalHandler
8. Ajouter `Organization::setCurrent(null)` apres les boucles tenant dans le scheduler

### Backlog (items audit non traites)

9. Implementer l'idempotence des retries Slack (cache event ID)
10. Standardiser DeleteOrder (rejeter en locked comme Create/Update)
11. Cacher `teamInfo()` et `isAdmin()` (Cache::remember)
12. Corriger le side effect dans DashboardStateResolver
13. Eager-load vendor.media pour eviter N+1
14. Ajouter l'index composite `['status', 'deadline_at']` sur lunch_sessions
