# Phase 1a -- Quick Wins Engagement

**Date** : 22 fevrier 2026
**Phase** : 1a (sous-phase de Phase 1 - Retention & Engagement)
**Statut** : A implementer AVANT le lancement beta
**Stories concernees** : 4.7, 4.8, 6.3, 8.3, 8.5

---

## 1. Objectif

Implementer les fonctionnalites a fort impact et faible effort qui boostent l'engagement des la beta. Ces features sont des prerequis au lancement : elles transforment un outil transactionnel en un produit qui genere de l'engagement organique.

Sans ces quick wins, le MVP fonctionne mais ne retient pas. L'audit business (section 3.3) identifie clairement la faille : "Zero mecanisme de retention. Rien ne ramene l'utilisateur le lendemain."

---

## 2. Contexte & Justification

### 2.1 Pourquoi ces quick wins sont critiques pour la beta

Le MVP est complet (46 stories). Il couvre le flow complet de coordination dejeuner. Mais le funnel d'engagement estime dans l'audit business montre un probleme structurel :

```
100% -- Voient le message channel
 60% -- Ouvrent /lunch
 40% -- Regardent les propositions
 25% -- Passent une commande
 15% -- Reviennent le lendemain         <-- Le gouffre
```

Le passage de 25% (conversion) a 15% (retention J1) est le point critique. Ces deux quick wins attaquent directement ce gouffre :

- **Le rappel pre-deadline** agit sur la conversion : il recupere les utilisateurs qui ont vu le message mais n'ont pas commande. L'audit estime +20% de commandes grace a ce levier.
- **Le message de bienvenue** agit sur la viralite : chaque nouvel utilisateur devient une publicite gratuite pour le bot dans le channel.

### 2.2 Pourquoi les deployer AVANT la beta

La premiere semaine de beta est decisive. Le plan d'adoption (audit business, section 6.2) fixe un objectif de 3-5 commandes/jour en semaine 1 et 50% d'adoption en semaine 2. Sans mecanismes de nudge, ces chiffres sont irrealistes.

Deployer ces features apres la beta reviendrait a mesurer la retention d'un produit incomplet. Les resultats seraient fausses, et le risque de "jour mort" (faille #1 de l'audit) serait maximal.

### 2.3 Ratio impact/effort

L'audit business classe explicitement le "Rappel 5 min avant deadline" dans les QUICK WINS (impact haut, effort faible). Le message de bienvenue est un viral hook identifie (hook #1, section 4.3) avec un effort de developpement minimal.

Ces deux features partagent des caracteristiques communes :
- Pas de nouveau modele de donnees
- Pas de nouvelle interaction utilisateur (pas de modal, pas de bouton)
- Logique metier simple (requete + message)
- Testables unitairement sans infrastructure Slack

---

## 3. Stories detaillees

### 3.1 Rappel pre-deadline

**Stories roadmap** : 4.7, 6.3, 8.3

#### User Story

> En tant qu'utilisateur qui n'a pas encore commande, je recois un rappel dans le channel quelques minutes avant la deadline, avec le nombre de collegues qui ont deja commande, pour que je sois incite a passer ma commande avant qu'il soit trop tard.

#### Description fonctionnelle

Le scheduler declenche un rappel automatique X minutes avant la deadline de chaque session ouverte. Le message est poste dans le channel lunch, en reply du thread de la session. Il combine deux leviers psychologiques : l'urgence (compte a rebours) et la preuve sociale (nombre de participants).

#### Criteres d'acceptation

1. **Declenchement automatique**
   - Le scheduler evalue chaque minute si une session ouverte a une deadline dans les X prochaines minutes (configurable, defaut : 10 min)
   - Le rappel est envoye une seule fois par session (pas de repetition si le scheduler repasse)
   - Le rappel n'est PAS envoye si la session est deja verrouillees, fermee, ou si aucune proposition n'existe
   - Le rappel n'est PAS envoye si la session n'a aucune commande (personne n'a participe, inutile de rappeler)

2. **Contenu du message**
   - Format : "Plus que X minutes pour commander ! Y collegues ont deja passe commande."
   - X = nombre de minutes restantes avant la deadline (arrondi a la minute)
   - Y = nombre total de commandes uniques (par utilisateur) sur toutes les propositions de la session
   - Le message mentionne le nombre de propositions actives si > 1 : "sur Z propositions"

3. **Emplacement du message**
   - Poste dans le channel lunch, en reply du thread de la session (utilise `provider_message_ts`)
   - N'utilise PAS de @channel ou @here (trop intrusif pour un rappel quotidien)
   - Le message n'est pas ephemere : il est visible par tous

4. **Idempotence**
   - Un flag ou un mecanisme empeche l'envoi multiple (ex: champ `reminder_sent_at` sur LunchSession, ou cache)
   - Si le scheduler tourne toutes les minutes et que le delai de rappel est 10 min, le rappel ne doit partir qu'une seule fois

5. **Configuration**
   - Le delai de rappel est configurable via `config/lunch.php` (cle : `reminder_minutes_before`, defaut : 10)
   - Le rappel peut etre desactive globalement via config (cle : `reminder_enabled`, defaut : true)

#### Considerations techniques

**Scheduler** (`routes/console.php`)

Un nouveau bloc Schedule::call qui s'execute chaque minute, similaire au pattern existant de `LockExpiredSessions`. Le code itere sur les organisations, identifie les sessions dont la deadline est dans les X prochaines minutes, et envoie le rappel si pas deja fait.

**Idempotence**

Deux options :
- Option A : Ajouter une colonne `reminder_sent_at` (nullable timestamp) sur `lunch_sessions`. Simple, explicite, testable. Migration legere.
- Option B : Utiliser le cache Laravel (`Cache::has("reminder:{$session->id}")`) pour eviter les doublons. Pas de migration, mais moins robuste en cas de restart.

Recommandation : Option A. Une migration legere est preferable a une dependance au cache pour un comportement critique.

**Action**

Creer une Action `SendPreDeadlineReminder` dans `app/Actions/LunchSession/` qui :
- Recoit une LunchSession
- Verifie les preconditions (session ouverte, deadline dans la fenetre, rappel pas encore envoye, au moins 1 commande)
- Calcule les stats (nombre de commandes, nombre de minutes restantes)
- Appelle SlackMessenger pour poster le message
- Met a jour `reminder_sent_at`

**SlackMessenger**

Ajouter une methode `postPreDeadlineReminder(LunchSession $session, int $minutesLeft, int $orderCount)` qui construit et poste le message.

**Multi-tenant**

Le scheduler itere deja sur les organisations (pattern existant). Le rappel respecte le meme pattern.

#### Flow UX

```
[Chaque minute] Scheduler s'execute
    |
    +-- Pour chaque organisation :
    |     |
    |     +-- Trouver les sessions ouvertes avec deadline dans [now, now+X min]
    |     |
    |     +-- Pour chaque session trouvee :
    |           |
    |           +-- reminder_sent_at est null ?
    |           |     NON --> skip
    |           |     OUI --> continuer
    |           |
    |           +-- Au moins 1 commande sur la session ?
    |           |     NON --> skip
    |           |     OUI --> continuer
    |           |
    |           +-- Calculer minutes restantes et nombre de commandes
    |           |
    |           +-- Poster le rappel dans le thread de la session
    |           |
    |           +-- Mettre a jour reminder_sent_at
    |
    [Channel Slack]
    |
    +-- "Plus que 8 minutes pour commander ! 6 collegues ont deja passe commande."
```

#### Metriques d'impact attendues

| Metrique | Avant | Cible | Comment mesurer |
|----------|-------|-------|-----------------|
| Commandes apres rappel | 0 | +20% des commandes totales arrivent apres le rappel | Comparer le nb de commandes passees apres `reminder_sent_at` vs avant |
| Taux de conversion session | ~25% | ~35% | Nb de commandeurs uniques / nb de membres du channel |
| Sessions sans commande | ~30% | ~15% | Sessions cloturees avec 0 commande |

---

### 3.2 Message de bienvenue

**Stories roadmap** : 4.8, 8.5

#### User Story

> En tant que nouvel utilisateur, quand je passe ma premiere commande via Lunch Bot, un message de bienvenue est poste dans le channel pour celebrer mon arrivee et montrer aux autres collegues que le bot est utilise.

#### Description fonctionnelle

Lors de la creation d'une commande (ou d'une demande Quick Run), le systeme verifie si c'est la premiere fois que cet utilisateur commande. Si oui, un message de bienvenue est poste dans le channel. Ce message a un double objectif : accueillir le nouvel utilisateur et servir de viral hook en rendant visible l'adoption du bot.

#### Criteres d'acceptation

1. **Detection du premier usage**
   - La premiere commande est detectee au moment de la creation d'un Order ou d'un QuickRunRequest
   - La verification se fait sur l'ensemble des commandes de l'organisation (pas juste la session du jour)
   - Un utilisateur qui a deja commande sur une session precedente ne recoit PAS de message de bienvenue
   - La detection couvre les deux cas d'usage : Session Dejeuner ET Quick Run

2. **Contenu du message**
   - Format : "Bienvenue <@userId> ! C'est sa premiere commande avec Lunch Bot. X collegues l'utilisent deja."
   - X = nombre d'utilisateurs uniques ayant au moins 1 commande dans l'organisation (hors le nouvel utilisateur)
   - Le message est chaleureux mais sobre. Pas de surenchere (pas de confettis, pas de 10 lignes)

3. **Emplacement du message**
   - Poste dans le channel lunch
   - Pour une session dejeuner : en reply du thread de la session (`provider_message_ts`)
   - Pour un Quick Run : en reply du thread du Quick Run (`provider_message_ts`)
   - Le message n'est PAS ephemere : sa visibilite publique EST la feature (viral hook)

4. **Idempotence**
   - Le message de bienvenue n'est envoye qu'une seule fois par utilisateur par organisation, meme si l'utilisateur commande plusieurs fois dans la meme journee
   - Si la commande est supprimee puis recree, pas de nouveau message de bienvenue

5. **Edge cases**
   - Si le bot demarre avec un seul utilisateur, le compteur affiche "0 collegues" ou une variante : "Soyez le premier a utiliser Lunch Bot !" ou n'affiche pas le compteur
   - Si la commande echoue apres le message de bienvenue, ce n'est pas grave (le message reste, la commande sera retentee)

#### Considerations techniques

**Detection du premier usage**

La requete de detection est simple :

```php
$isFirstOrder = ! Order::where('provider_user_id', $userId)->exists()
    && ! QuickRunRequest::where('provider_user_id', $userId)->exists();
```

Cette requete doit etre executee AVANT la creation de la commande, ou dans la meme transaction avec la commande.

**Point d'integration**

Deux options :

- Option A : Integrer dans les Actions existantes (`CreateOrder`, `CreateQuickRunRequest`). Apres la creation, verifier si c'est la premiere commande et poster le message. Simple, mais melange la logique metier et la notification.
- Option B : Utiliser un event Laravel (`OrderCreated`) et un listener (`SendWelcomeMessage`). Plus propre architecturalement, mais ajoute de l'indirection.

Recommandation : Option A pour la Phase 1a. La logique est simple (une verification + un message). L'evenement sera utile quand on aura plus de side-effects (badges, stats, etc.) en Phase 2. Pour l'instant, garder ca simple.

**Compteur d'utilisateurs**

```php
$userCount = Order::distinct('provider_user_id')->count('provider_user_id')
    + QuickRunRequest::distinct('provider_user_id')->count('provider_user_id');
// Dedupliquer les utilisateurs presents dans les deux tables
```

Alternative plus precise : requete UNION pour dedupliquer. Ou une approche simplifiee : compter uniquement les commandes Order (le Quick Run est un cas secondaire).

Recommandation : compter les `provider_user_id` distincts dans la table `orders` uniquement. C'est le cas principal, et ca evite une requete complexe. Le chiffre sera legerement sous-evalue mais acceptable.

**SlackMessenger**

Ajouter une methode `postWelcomeMessage(string $channelId, string $userId, int $existingUserCount, ?string $threadTs = null)`.

#### Flow UX

```
[Utilisateur] Clique "Commander" --> Remplit le modal --> Soumet
    |
    +-- CreateOrder::handle()
          |
          +-- Verifier si c'est la premiere commande de cet utilisateur
          |     |
          |     +-- Requete : Order::where('provider_user_id', $userId)->exists()
          |     |
          |     +-- Si OUI (existe deja) --> skip
          |     +-- Si NON (premier usage) --> continuer
          |
          +-- Creer la commande
          |
          +-- Compter les utilisateurs uniques existants
          |
          +-- Poster le message de bienvenue dans le thread
          |
          [Channel Slack - thread de la session]
          |
          +-- "Bienvenue @Sarah ! C'est sa premiere commande avec Lunch Bot.
          |    12 collegues l'utilisent deja."
```

#### Metriques d'impact attendues

| Metrique | Avant | Cible | Comment mesurer |
|----------|-------|-------|-----------------|
| Taux d'adoption semaine 2 | ~30% | ~50% | % du channel qui a commande au moins 1 fois |
| Nouveaux utilisateurs / semaine | organique | +30% vs sans bienvenue | Compter les `isFirstOrder = true` par semaine |
| Engagement post-bienvenue | N/A | 60% repassent commande dans les 3 jours | Retention du nouvel utilisateur apres premiere commande |

---

## 4. Priorite et sequencage

### 4.1 Ordre d'implementation recommande

| Ordre | Story | Justification |
|-------|-------|---------------|
| 1 | Rappel pre-deadline | Impact immediat sur la conversion. Agit sur TOUS les utilisateurs, pas seulement les nouveaux. Chaque jour de beta sans rappel = commandes perdues. |
| 2 | Message de bienvenue | Agit sur la viralite. Le ROI augmente avec le temps (chaque nouvel utilisateur en amene d'autres). Moins urgent que le rappel car l'effet est cumulatif. |

### 4.2 Dependances

```
Rappel pre-deadline
  +-- Migration : ajouter reminder_sent_at sur lunch_sessions
  +-- Config : ajouter reminder_minutes_before et reminder_enabled dans config/lunch.php
  +-- Action : SendPreDeadlineReminder
  +-- SlackMessenger : postPreDeadlineReminder()
  +-- Scheduler : nouveau bloc dans routes/console.php
  +-- Tests unitaires de l'Action
  +-- Aucune dependance avec d'autres features

Message de bienvenue
  +-- Modification : CreateOrder (ou listener)
  +-- Modification : CreateQuickRunRequest (si on couvre le Quick Run)
  +-- SlackMessenger : postWelcomeMessage()
  +-- Tests unitaires
  +-- Aucune dependance avec le rappel (les deux sont independants)
```

Les deux stories sont completement independantes. Elles peuvent etre developpees en parallele ou sequentiellement sans conflit.

### 4.3 Estimation de la charge

| Story | Taille | Detail |
|-------|--------|--------|
| Rappel pre-deadline | **S** (Small) | 1 migration, 1 Action, 1 methode SlackMessenger, 1 bloc scheduler, config, tests. Pas de UI, pas de modal, pas d'interaction. |
| Message de bienvenue | **S** (Small) | 1 modification Action, 1 methode SlackMessenger, tests. Encore plus simple que le rappel (pas de scheduler, pas de migration). |
| **Total Phase 1a** | **S** | 1-2 jours de developpement pour les deux stories, tests inclus. |

---

## 5. Risques et mitigations

### 5.1 Risque : Spam percu

**Description** : Les utilisateurs peuvent percevoir le rappel comme du bruit dans le channel, surtout s'il s'ajoute au kickoff, aux messages de proposition et aux notifications de commande.

**Probabilite** : Moyenne

**Impact** : Haut (les utilisateurs mutent le channel, l'adoption chute)

**Mitigations** :
- Le rappel est en reply du thread, pas en message principal du channel
- Pas de @channel ni @here
- Un seul rappel par session (pas de sequence de rappels)
- Le rappel est configurable et desactivable (`reminder_enabled`)
- Surveiller le feedback beta des les premiers jours

### 5.2 Risque : Message de bienvenue genant

**Description** : Certains utilisateurs n'aiment pas etre mis en avant publiquement. Le message de bienvenue peut creer de l'inconfort.

**Probabilite** : Faible (la culture Slack est generalement decontractee)

**Impact** : Faible a moyen (un utilisateur gene, pas un rejet massif)

**Mitigations** :
- Ton neutre et chaleureux, pas de surenchere
- Pas de mention des details de la commande (juste "premiere commande")
- Possibilite d'ajouter une option de desactivation plus tard si le feedback le demande
- Le message est dans le thread, moins visible que dans le channel principal

### 5.3 Risque : Compteur d'utilisateurs bas au debut

**Description** : Au lancement beta, "1 collegue utilise deja Lunch Bot" n'est pas tres convaincant comme preuve sociale.

**Probabilite** : Certaine (les premiers jours, le compteur sera bas)

**Impact** : Faible (le message reste positif meme avec un petit chiffre)

**Mitigations** :
- Si le compteur est 0, adapter le message : "Bienvenue @Sarah ! Premiere commande avec Lunch Bot."
- A partir de 3+, afficher le compteur : "X collegues l'utilisent deja."
- Le compteur augmente naturellement chaque jour, le probleme se resout de lui-meme

### 5.4 Risque : Rappel envoye apres verrouillage (race condition)

**Description** : Le scheduler de rappel et le scheduler de verrouillage tournent tous les deux chaque minute. Si le verrouillage se fait avant le rappel dans le meme cycle, le rappel peut etre envoye sur une session deja verrouillee.

**Probabilite** : Faible (les deux tournent en sequence dans le meme process)

**Impact** : Faible (un rappel "fantome" est inesthetique mais pas bloquant)

**Mitigations** :
- L'Action `SendPreDeadlineReminder` verifie le statut de la session (`isOpen()`) avant d'envoyer
- Placer le bloc rappel AVANT le bloc verrouillage dans `routes/console.php`

---

## 6. Criteres de succes

### 6.1 KPIs quantitatifs

| KPI | Definition | Cible a 2 semaines | Comment mesurer |
|-----|-----------|---------------------|-----------------|
| Taux de commandes post-rappel | % des commandes passees apres l'envoi du rappel | 15-20% | Comparer `orders.created_at` avec `lunch_sessions.reminder_sent_at` |
| Conversion session | Utilisateurs ayant commande / utilisateurs du channel | 35%+ (vs 25% sans rappel) | Compteur de commandeurs uniques / taille estimee du channel |
| Adoption cumulee | % du groupe pilote ayant commande au moins 1 fois | 50%+ en semaine 2 | Utilisateurs uniques / taille du groupe pilote |
| Viralite bienvenue | Nb de premiers utilisateurs par semaine | 3-5 nouveaux / semaine (sur un groupe de 15) | Compter les messages de bienvenue |
| Retention J3 post-bienvenue | % des nouveaux utilisateurs qui recommandent dans les 3 jours | 60%+ | Suivi par `provider_user_id` |

### 6.2 KPIs qualitatifs

| Signal | Positif | Negatif |
|--------|---------|---------|
| Reaction au rappel | Des commandes arrivent dans les minutes suivantes | Les utilisateurs ignorent systematiquement |
| Reaction a la bienvenue | Reactions Slack (emoji) sur le message, commentaires | Demandes de desactivation, plaintes |
| Feedback spontane | "Ah oui merci, j'avais oublie de commander" | "Arretez de me spammer" |

### 6.3 Critere de succes global Phase 1a

La Phase 1a est un succes si :
1. Le rappel genere au moins 2 commandes supplementaires par jour (sur un groupe de 15 personnes)
2. Le message de bienvenue est percu positivement (aucune demande de desactivation en 2 semaines)
3. L'adoption cumulative atteint 50% du groupe pilote en 2 semaines de beta

---

## 7. Annexes

### 7.1 Exemples de messages Slack

**Rappel pre-deadline**

```
Plus que 8 minutes pour commander ! 6 collegues ont deja passe commande.
```

Variante avec plusieurs propositions :

```
Plus que 5 minutes pour commander ! 9 collegues ont deja passe commande sur 2 propositions.
```

**Message de bienvenue -- premiers utilisateurs (compteur < 3)**

```
Bienvenue @Sarah ! Premiere commande avec Lunch Bot.
```

**Message de bienvenue -- adoption en cours (compteur >= 3)**

```
Bienvenue @Thomas ! C'est sa premiere commande avec Lunch Bot. 8 collegues l'utilisent deja.
```

### 7.2 Configuration ajoutee

```php
// config/lunch.php
return [
    // ... config existante ...

    'reminder_enabled' => env('LUNCH_REMINDER_ENABLED', true),
    'reminder_minutes_before' => env('LUNCH_REMINDER_MINUTES', 10),

    'welcome_message_enabled' => env('LUNCH_WELCOME_ENABLED', true),
];
```

### 7.3 Migration

```php
// Ajout sur lunch_sessions
Schema::table('lunch_sessions', function (Blueprint $table) {
    $table->timestamp('reminder_sent_at')->nullable();
});
```

### 7.4 Lien avec la Phase 1 complete

La Phase 1a represente 5 stories sur les 21 de la Phase 1 complete. Les stories restantes sont reparties dans les sous-phases suivantes :

| Sous-phase | Stories | Description |
|------------|---------|-------------|
| **1b** - Closing & Payment | 10.1 a 10.7 | DM de paiement, QR code SEPA, suivi des paiements |
| **1c** - Valeur Individuelle | 3.8, 3.9, 8.1, 8.2 | Favoris, historique personnel |
| **1d** - Visibilite Collective | 4.9, 4.10, 6.4, 8.4, 8.6 | Friday Digest, recap enrichi |

Sequencage recommande :
- **1a** (ce document) : Rappel + Bienvenue -- deployer AVEC la beta
- **1b** : Closing & Payment -- deployer en semaine 1-3 de beta (killer feature)
- **1c** : Favoris + Historique -- deployer en semaine 3-4 de beta (retention)
- **1d** : Recaps enrichis + Friday Digest -- deployer en semaine 4-5 de beta (engagement social)
