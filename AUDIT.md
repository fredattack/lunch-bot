# Audit Complet - Lunch Bot MVP

**Date**: 15 fevrier 2026
**Version auditee**: `f3d40e5` (branche `main`)
**Objectif**: Evaluer la readiness beta et identifier les blockers pour le ship

---

## Verdict Global

| Dimension | Score | Status |
|-----------|-------|--------|
| Architecture | 8/10 | Solide |
| Qualite du code | 7/10 | Bon avec dette technique ciblee |
| Tests | 7.5/10 | Bonne couverture, gaps identifies |
| Securite | 6/10 | Problemes critiques a corriger |
| Performance | 7/10 | Acceptable pour beta, optimisations a planifier |
| Pret pour la beta | **NON** | 3 blockers critiques a resoudre |

**280 tests passent. 0 echecs. Code style Pint : OK.**

---

## 1. BLOCKERS CRITIQUES (a corriger avant beta)

### 1.1 SECURITE : Commande DevResetDatabase en production

**Fichiers**: `SlackInteractionHandler.php:554`, `DashboardBlockBuilder.php:47`

```php
case SlackAction::DevResetDatabase->value:
    if ($userId !== 'U08E9Q2KJGY') {  // Hardcoded user ID
        return;
    }
    Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
```

**Probleme**: Un `migrate:fresh` derriere un simple check de user ID hardcode. Si ce user ID leake ou si le bouton est expose par erreur, toute la base de production est detruite. Ce n'est pas un risque hypothetique - c'est une bombe a retardement.

**Action immediate**:
- Supprimer completement `DevResetDatabase` et `DevExportVendors` du handler
- Ou les conditionner a `app()->environment('local')` au minimum
- Extraire `DEV_USER_ID` vers `config('slack.dev_user_id')` si conserve

### 1.2 SCHEDULER NON IMPLEMENTE

**Fichier**: `routes/console.php:10-20`

Le scheduler est entierement commente. Cela signifie :
- Aucun kickoff quotidien automatique (le coeur du produit)
- Aucun verrouillage automatique des sessions expirees
- L'app depend a 100% d'interactions manuelles pour demarrer une journee

**Action immediate**:
```php
// routes/console.php
Schedule::call(function () {
    $action = app(CreateLunchSession::class);
    // Creer session + poster kickoff pour chaque orga active
})->dailyAt(config('lunch.post_time'))->timezone(config('lunch.timezone'));

Schedule::call(function () {
    app(LockExpiredSessions::class)->handle();
})->everyMinute();
```

### 1.3 AUCUN RETRY / RATE LIMITING SUR L'API SLACK

**Fichier**: `SlackService.php:146-166`

Chaque appel API Slack est fire-and-forget. En cas de rate limit (HTTP 429) ou timeout transitoire, l'operation echoue silencieusement. Pour un produit qui repose a 100% sur Slack, c'est un single point of failure.

**Action immediate**:
```php
private function client(string $token): PendingRequest
{
    return Http::withToken($token)
        ->asJson()
        ->retry(3, 1000, fn ($e, $request) => $e->response?->status() === 429)
        ->timeout(10);
}
```

---

## 2. PROBLEMES MAJEURS (a corriger pendant/juste apres la beta)

### 2.1 Race condition dans DelegateRole

**Fichier**: `DelegateRole.php`

Contrairement a `AssignRole` qui utilise `lockForUpdate()` avec une transaction, `DelegateRole` fait un read-then-write sans verrou. Deux delegations simultanees pourraient corrompre l'etat.

**Correction**: Ajouter `DB::transaction()` + `lockForUpdate()` comme dans `AssignRole`.

### 2.2 AssignRole force le status Ordering sans validation

**Fichier**: `AssignRole.php:26`

```php
$locked->status = ProposalStatus::Ordering;
```

Si le proposal est deja en status `Placed` ou `Received`, assigner un role le remet a `Ordering`. Transition d'etat invalide.

**Correction**: Ajouter un guard :
```php
if ($locked->status === ProposalStatus::Open) {
    $locked->status = ProposalStatus::Ordering;
}
```

### 2.3 Fuite de fichiers temporaires

**Fichier**: `SlackService.php:129-132`

`downloadFile()` cree un fichier temp qui n'est jamais nettoye par l'appelant (`processFileUpload` dans le handler). Sur un serveur en production, ces fichiers s'accumulent indefiniment.

**Correction**: Ajouter un `try/finally` avec `@unlink($tempPath)` dans `processFileUpload`.

### 2.4 Retries Slack acceptes aveuglement

**Fichier**: `SlackController.php:13-14, 48-49`

```php
if ($request->header('X-Slack-Retry-Num')) {
    return response('', 200);
}
```

Si le premier traitement etait lent mais a abouti, le retry est ignore (correct). Mais si le premier traitement a echoue, le retry est aussi ignore (problematique). Il manque un mecanisme d'idempotence base sur l'event ID.

**Correction a terme**: Stocker les event IDs traites en cache (TTL 5 min) et ne rejeter que les doublons effectifs.

### 2.5 Inconsistance des validations de session

| Action | Check | Locked autorise ? |
|--------|-------|-------------------|
| `CreateOrder` | `!isOpen()` | Non |
| `DeleteOrder` | `isClosed()` | Oui |
| `UpdateOrder` | `!isOpen()` | Non |

`DeleteOrder` permet de supprimer une commande quand la session est verrouillee (deadline passee). C'est probablement un bug - si on ne peut plus creer/modifier, on ne devrait pas pouvoir supprimer non plus.

### 2.6 Duplication extractTeamId()

**Fichiers**: `VerifySlackSignature.php` et `ResolveOrganization.php`

Logique identique dupliquee dans deux middlewares. Risque de divergence lors de futures modifications.

**Correction**: Extraire vers un trait `ExtractsSlackTeamId` ou une methode sur la Request.

---

## 3. DETTE TECHNIQUE

### 3.1 Fichiers God Object

| Fichier | Lignes | Responsabilites |
|---------|--------|-----------------|
| `SlackBlockBuilder.php` | 1426 | Modals + messages + formatage |
| `SlackInteractionHandler.php` | 1283 | Routing + validation + orchestration |
| `DashboardBlockBuilder.php` | 725 | Dashboard UI |

`SlackInteractionHandler` est un switch case de 60+ branches. Chaque nouveau bouton Slack ajoute du code dans ce fichier. Ca ne scale pas.

**Recommandation**: Extraire des Handler classes par domaine :
- `OrderInteractionHandler` (creation, edition, suppression, ajustement prix)
- `ProposalInteractionHandler` (proposition, gestion, cloture)
- `SessionInteractionHandler` (kickoff, cloture session)
- `VendorInteractionHandler` (CRUD, liste, recherche)

### 3.2 Code mort et deprecated

| Element | Fichier | Status |
|---------|---------|--------|
| `postProposalMessage()` | SlackMessenger:103 | `@deprecated` - jamais appele |
| `updateProposalMessage()` | SlackMessenger:124 | `@deprecated` mais ENCORE UTILISE (6 call sites) |
| `lunchDashboardModal()` | SlackBlockBuilder | Remplace par DashboardBlockBuilder |
| `dashboardProposalBlocks()` | SlackBlockBuilder | Appelee uniquement par la methode ci-dessus |
| Legacy action handlers | SlackInteractionHandler:304-518 | Doublons des actions Dashboard |
| `SlackActions.php` (class) | SlackActions | Remplacee par enum `SlackAction` |

**Correction `updateProposalMessage()`**: Retirer le tag `@deprecated` (c'est actif) OU migrer les 6 call sites vers la nouvelle implementation. Decider.

### 3.3 Internationalisation inexistante

~129 chaines en francais hardcodees dans les Block Builders. Zero infrastructure i18n.

**Exemples** :
```php
'Dejeuner du {$date}'
'Proposer une enseigne'
'Je suis runner'
'Aucune commande pour le moment.'
```

Pour la beta c'est acceptable si le public cible est francophone. Mais c'est un mur technique pour toute expansion. L'enum `OrderingMode` a des labels en francais, les Actions lancent des exceptions tantot en francais, tantot en anglais.

**Strategie recommandee pour post-beta**: Extraire vers `lang/fr/slack.php` avec le helper `__()`.

### 3.4 Duplication entre Block Builders

Methodes identiques entre `SlackBlockBuilder` et `DashboardBlockBuilder` :
- `button()` - copier-coller exact
- `formatTime()` / `formatDateLabel()` - meme logique
- Fulfillment type labels - redondants
- Responsible/role text - recalcule differemment

**Correction**: Extraire un trait `SlackBlockHelpers` partage.

### 3.5 Formatage de prix inconsistant

```php
// Sans devise
number_format((float) $order->price_estimated, 2)
// Avec devise
number_format((float) $order->price_estimated, 2).' EUR'
```

Melange des deux approches dans les builders. Creer un helper `formatPrice($amount): string`.

---

## 4. TESTS

### 4.1 Etat actuel

- **280 tests**, 537 assertions, 4.72s
- **Actions**: Excellente couverture (12/12 testees)
- **Policies**: Bien couvertes (3/3)
- **Middleware**: Bien couvertes (3/3)
- **Multi-tenant**: Test d'isolation dedie
- **Dashboard states**: 18 tests couvrant les 6 etats

### 4.2 Gaps critiques

| Composant | Tests | Risque |
|-----------|-------|--------|
| `SlackService` (HTTP client) | Aucun | ELEVE - coeur de l'integration |
| `SlackMessenger` (orchestration) | Aucun | ELEVE - logique complexe |
| `LogRequest` middleware | Aucun | MOYEN |
| `Actor` class | Aucun | MOYEN |
| Integration E2E workflow complet | Aucun | ELEVE |

### 4.3 Cas limites manquants

- **AssignRole** : pas de test de concurrence (2 users cliquent en meme temps)
- **UpdateOrder** : pas de test avec prix negatifs, overflow, ou plus de 2 decimales
- **LockExpiredSessions** : pas de test DST (changement d'heure)
- **CreateLunchSession** : pas de test avec deadline dans le passe

### 4.4 Action manquante documentee

`CLAUDE.md` mentionne `AdjustOrderPrice` dans la liste des Actions, mais cette Action n'existe pas en tant que classe. La logique d'ajustement de prix est inline dans `SlackInteractionHandler`. Soit creer l'Action, soit mettre a jour la doc.

---

## 5. PERFORMANCE

### 5.1 Appels API non caches

`SlackService::teamInfo()` est appele a chaque ouverture de dashboard via `DashboardStateResolver::resolveLocale()`. L'info team change rarement.

**Correction**: `Cache::remember("slack_team_{$teamId}", 3600, fn () => ...)`.

### 5.2 Side effect dans le Resolver

`DashboardStateResolver:171-174` ecrit en base (`$organization->update(['locale' => ...])`) a chaque resolution de dashboard. Un resolver devrait etre read-only.

**Correction**: Deplacer vers un job/listener ou dans le middleware d'installation.

### 5.3 N+1 potentiel sur vendor media

Quand les proposals sont chargees avec `->with(['vendor', 'orders'])`, les medias du vendor (logo) ne sont pas eager-loaded. Chaque `$vendor->getLogoThumbUrl()` dans le builder declenche une requete supplementaire.

**Correction**: `->with(['vendor.media', 'orders'])`.

### 5.4 Index manquant

Le query pattern de `LockExpiredSessions` filtre sur `status` + `deadline_at` mais aucun index composite n'existe pour cette combinaison.

**Correction**: Migration ajoutant `['status', 'deadline_at']` sur `lunch_sessions`.

---

## 6. SECURITE

### 6.1 Bilan

| Element | Status |
|---------|--------|
| Signature Slack HMAC-SHA256 | OK - `hash_equals()` utilise |
| Fenetre de replay (5 min) | Acceptable (recommandation Slack) |
| Tokens chiffres en base | OK - `encrypted` cast |
| Middleware organization | OK - isole les tenants |
| CSRF sur routes API | N/A - signature Slack remplace |
| Injection SQL | OK - Eloquent partout |
| XSS | N/A - pas de frontend web |

### 6.2 Points d'attention

- **DevResetDatabase** : Voir blocker 1.1
- **Policies sans check org explicite** : Les policies se fient au global scope. Defense en profondeur insuffisante.
- **isAdmin() appelle l'API Slack a chaque fois** : Pas de cache. Un attaquant pourrait forcer des appels API repetitifs. Ajouter un cache de 5 min.

---

## 7. ARCHITECTURE - POINTS FORTS

L'architecture est la vraie force de ce projet :

1. **Action Pattern** bien applique : chaque operation metier isolee, testable, reutilisable
2. **Multi-tenancy** propre via global scopes + Context API
3. **Provider abstraction** (`provider` au lieu de `slack_*`) : extensible a Teams/Discord
4. **Separation des couches** : Controller > Handler > Action > Model
5. **Dashboard State Machine** : DashboardState enum + resolver = UI previsible
6. **Policies** : autorisation propre via Actor au lieu de hacker le systeme auth Laravel
7. **Audit log sur Orders** : tracabilite integree

---

## 8. PLAN D'ACTION POUR SHIPPER LA BETA

### Phase 0 : Blockers (1-2 jours)

- [ ] Supprimer ou proteger les actions Dev (DevResetDatabase, DevExportVendors)
- [ ] Implementer le scheduler (kickoff quotidien + lock sessions expirees)
- [ ] Ajouter retry/timeout sur SlackService

### Phase 1 : Stabilisation (2-3 jours)

- [ ] Corriger la race condition DelegateRole (ajouter transaction + lock)
- [ ] Corriger le guard de status dans AssignRole
- [ ] Corriger la fuite de fichiers temporaires dans downloadFile/processFileUpload
- [ ] Standardiser les validations de session (Locked vs Closed)
- [ ] Cacher teamInfo() et isAdmin()

### Phase 2 : Tests critiques (1-2 jours)

- [ ] Tests pour SlackService (mock HTTP)
- [ ] Tests pour SlackMessenger
- [ ] Test d'integration workflow complet (session > proposal > order > close)
- [ ] Tests de concurrence pour AssignRole

### Phase 3 : Nettoyage (1 jour)

- [ ] Supprimer le code mort (lunchDashboardModal, legacy handlers, SlackActions class)
- [ ] Corriger le tag deprecated sur updateProposalMessage
- [ ] Extraire extractTeamId en trait partage
- [ ] Mettre a jour CLAUDE.md (supprimer reference AdjustOrderPrice)

### Phase 4 : Beta launch (1 jour)

- [ ] Configurer le scheduler sur le serveur (cron)
- [ ] Configurer les variables d'environnement production
- [ ] Verifier les scopes OAuth de l'app Slack
- [ ] Tester le flow complet dans un workspace de test
- [ ] Deployer

---

## 9. RECOMMANDATIONS POST-BETA

### Court terme (sprint suivant)

1. **Refactorer SlackInteractionHandler** en handlers par domaine
2. **Extraire les Block Builder helpers** en trait partage
3. **Ajouter des events** (OrderCreated, RoleAssigned, SessionClosed) pour decouplage
4. **Implementer les queues** pour les appels Slack API (ShouldQueue)

### Moyen terme

5. **Internationalisation** : extraire les 129 chaines vers `lang/fr/slack.php`
6. **Analytics de base** : nombre de commandes par jour, vendors populaires, taux d'adoption
7. **Onboarding automatique** : OAuth install flow pour nouveaux workspaces
8. **Historique utilisateur** : commandes passees, depenses, restaurants favoris

### Long terme

9. **Multi-provider** : adapter pour Microsoft Teams / Discord
10. **Dashboard web** : admin panel pour gestion des vendors et analytics
11. **Notifications intelligentes** : rappels deadline, suggestions basees sur l'historique
12. **Export comptable** : CSV/PDF des remboursements par periode

---

## 10. METRIQUES DU PROJET

| Metrique | Valeur |
|----------|--------|
| Fichiers PHP (app/) | 46 |
| Lignes de code (app/) | 6 154 |
| Tests | 280 (537 assertions) |
| Modeles | 7 |
| Actions | 12 |
| Enums | 6 |
| Migrations | 18 |
| Factories | 7 |
| Temps d'execution tests | 4.72s |
| Tables en base | 16 |
| Endpoints API | 2 |
| Actions Slack gerees | 60+ |

---

## Conclusion

Ce projet a des fondations architecturales solides. L'Action Pattern, le multi-tenant, et la separation des couches sont bien pensees. Le principal risque pour la beta n'est pas la qualite du code mais les 3 blockers critiques : la bombe DevResetDatabase, l'absence de scheduler, et l'absence de retry sur l'API Slack.

Une fois ces 3 points resolus + la stabilisation de Phase 1, le projet est shipable en beta. La dette technique identifiee (god objects, i18n, code mort) est geree et ne bloque pas le lancement - elle devra etre traitee dans les sprints suivants pour eviter qu'elle ne devienne un frein a l'evolution du produit.

**Estimation totale avant beta launch : 5-7 jours de travail.**
