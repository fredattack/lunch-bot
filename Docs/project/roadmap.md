# Roadmap - Lunch Bot

**Date** : 22 fevrier 2026
**Structure** : EPICs > Stories
**Phases** : MVP → Phase 1a (Quick Wins) → Phase 1b (Closing & Payment) → Phase 1c (Valeur Individuelle) → Phase 1d (Visibilite Collective & Admin Setup) → Phase 2 (Social & Discovery)

> Les stories marquees DONE sont deja implementees et testees. Les EPICs peuvent contenir un mix de DONE et TODO.

---

## EPIC 1 : Session Dejeuner

> Le coeur du produit. Coordonner une commande groupee depuis la proposition jusqu'a la cloture.

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 1.1 | En tant qu'utilisateur, je peux proposer un restaurant du catalogue pour le dejeuner en choisissant le type (pickup/delivery), la deadline et une note optionnelle | MVP | DONE |
| 1.2 | En tant qu'utilisateur, je peux proposer un nouveau restaurant en le creant a la volee avec nom, URL, type de cuisine et logo | MVP | DONE |
| 1.3 | En tant qu'utilisateur, je peux passer une commande sur une proposition ouverte avec une description et un prix estime | MVP | DONE |
| 1.4 | En tant qu'utilisateur, je peux modifier ma commande tant que la session est ouverte | MVP | DONE |
| 1.5 | En tant qu'utilisateur, je peux supprimer ma commande avec confirmation | MVP | DONE |
| 1.6 | En tant qu'utilisateur, je peux me porter volontaire comme runner ou orderer sur une proposition | MVP | DONE |
| 1.7 | En tant que runner, je peux deleguer mon role a un autre collegue | MVP | DONE |
| 1.8 | En tant que runner, je peux consulter le recapitulatif de toutes les commandes avant d'aller chercher | MVP | DONE |
| 1.9 | En tant que runner, je peux ajuster les prix finaux apres l'achat (prix reel vs estime) | MVP | DONE |
| 1.10 | En tant que runner, je peux cloturer la proposition une fois les commandes livrees | MVP | DONE |
| 1.11 | En tant que runner ou admin, je peux cloturer la session de la journee avec un recap des montants dus | MVP | DONE |
| 1.12 | En tant qu'utilisateur, quand la session est cloturee, je vois le recap dans le channel avec les montants que chacun doit | MVP | DONE |

---

## EPIC 2 : Catalogue Restaurants

> Gerer les restaurants disponibles pour les commandes.

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 2.1 | En tant qu'utilisateur, je peux voir la liste des restaurants actifs avec une recherche par nom | MVP | DONE |
| 2.2 | En tant qu'utilisateur, je peux ajouter un restaurant au catalogue avec nom, URL du menu et notes | MVP | DONE |
| 2.3 | En tant que createur ou admin, je peux modifier les infos d'un restaurant (nom, cuisine, URLs, types de livraison, statut actif) | MVP | DONE |
| 2.4 | En tant qu'utilisateur, je peux uploader un logo et un menu (PDF/image) pour un restaurant | MVP | DONE |
| 2.5 | En tant qu'utilisateur, je peux voir le logo du restaurant dans les propositions et le catalogue | MVP | DONE |
| 2.6 | En tant qu'utilisateur, je peux noter un restaurant apres une commande (pouce haut/bas) | Phase 2 | TODO |
| 2.7 | En tant qu'utilisateur, je vois les restaurants suggeres chaque matin en fonction de l'historique et de la rotation | Phase 2 | TODO |
| 2.8 | En tant qu'utilisateur, je vois un "Restaurant de la semaine" mis en avant pour decouvrir de nouveaux endroits | Phase 2 | TODO |

---

## EPIC 3 : Dashboard & Navigation

> L'interface principale du bot via la commande /lunch.

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 3.1 | En tant qu'utilisateur, je peux ouvrir le dashboard via /lunch et voir ma situation du jour | MVP | DONE |
| 3.2 | Si aucune proposition n'existe (S1), je vois un CTA pour demarrer une commande ou proposer un resto | MVP | DONE |
| 3.3 | Si des propositions existent sans que j'aie commande (S2), je vois les propositions avec un bouton "Commander" | MVP | DONE |
| 3.4 | Si j'ai commande (S3), je vois ma commande avec un bouton "Modifier" | MVP | DONE |
| 3.5 | Si je suis runner (S4), je vois les outils de gestion (recap, cloture, delegation) | MVP | DONE |
| 3.6 | Si tout est cloture (S5), je vois le resume et un bouton pour relancer | MVP | DONE |
| 3.7 | Pour un jour passe (S6), je vois l'historique en lecture seule | MVP | DONE |
| 3.8 | En tant qu'utilisateur, je peux consulter l'historique de mes commandes passees | Phase 1c | TODO |
| 3.9 | En tant qu'utilisateur, je peux recommander en 1 clic ma commande habituelle (favoris) | Phase 1c | TODO |

---

## EPIC 4 : Messages & Notifications

> Tout ce que le bot poste dans le channel Slack.

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 4.1 | Le bot poste un message de kickoff chaque matin a l'heure configuree avec les boutons d'action | MVP | DONE |
| 4.2 | Quand une proposition est creee, le bot poste un message dans le channel avec le nom du resto, le type et un bouton "Commander" | MVP | DONE |
| 4.3 | Le message de proposition se met a jour en temps reel (nombre de commandes, runner assigne) | MVP | DONE |
| 4.4 | A la deadline, le bot poste "Commandes verrouillees" | MVP | DONE |
| 4.5 | Quand un role est delegue, le bot annonce "Role transfere de @X a @Y" | MVP | DONE |
| 4.6 | A la cloture, le bot poste le recap avec les montants dus par personne | MVP | DONE |
| 4.7 | 5-10 min avant la deadline, le bot envoie un rappel : "Plus que X min, Y collegues ont commande" | Phase 1a | TODO |
| 4.8 | Quand un utilisateur commande pour la premiere fois, le bot poste un message de bienvenue | Phase 1a | TODO |
| 4.9 | Chaque vendredi, le bot poste un recap hebdomadaire (stats, top restos, MVP runner) | Phase 1d | TODO |
| 4.10 | A la cloture, le recap inclut des stats enrichies (moyenne par personne, runner mis en avant) | Phase 1d | TODO |

---

## EPIC 5 : Quick Run

> Le cas d'usage spontane : "Je vais chez X, quelqu'un veut quelque chose ?"

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 5.1 | En tant qu'utilisateur, je peux lancer un Quick Run en indiquant ou je vais et combien de temps les collegues ont pour repondre | MVP | DONE |
| 5.2 | Quand un Quick Run est lance, le bot poste un message dans le channel avec le lieu, le delai et un bouton "Ajouter une demande" | MVP | DONE |
| 5.3 | En tant que collegue, je peux ajouter une demande via un modal simple (description + prix estime optionnel) | MVP | DONE |
| 5.4 | Le message du Quick Run se met a jour en temps reel (nombre de demandes, noms des demandeurs) | MVP | DONE |
| 5.5 | En tant que runner du Quick Run, je peux cliquer "Je pars" pour verrouiller les demandes et voir le recap de ce que je dois acheter | MVP | DONE |
| 5.6 | En tant que runner du Quick Run, je peux ajuster les prix reels au retour et cloturer le run | MVP | DONE |
| 5.7 | A la cloture du Quick Run, le bot poste le recap final avec les montants dus au runner | MVP | DONE |
| 5.8 | En tant que collegue, je peux modifier ou supprimer ma demande tant que le runner n'est pas parti | MVP | DONE |
| 5.9 | Si le delai expire sans que le runner clique "Je pars", les demandes sont automatiquement verrouillees | MVP | DONE |

---

## EPIC 6 : Scheduler & Automatisation

> Les taches automatiques planifiees.

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 6.1 | Le bot cree automatiquement une session dejeuner et poste le kickoff chaque jour ouvrable a l'heure configuree | MVP | DONE |
| 6.2 | Le bot verrouille automatiquement les sessions dont la deadline est passee (check chaque minute) | MVP | DONE |
| 6.3 | Le bot envoie un rappel dans le channel X minutes avant la deadline | Phase 1a | TODO |
| 6.4 | Le bot poste un recap hebdomadaire chaque vendredi a 16h | Phase 1d | TODO |
| 6.5 | Le bot verrouille automatiquement les Quick Runs dont le delai est expire | MVP | DONE |

---

## EPIC 7 : Stabilite & Fiabilite MVP

> Les prerequis de stabilite pour un deploiement beta.

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 7.1 | Les outils de developpement (reset DB, export) sont inaccessibles en production | MVP | DONE |
| 7.2 | Les appels a l'API Slack sont reessayes automatiquement en cas d'echec ou de rate limit | MVP | DONE |
| 7.3 | Les fichiers temporaires (upload de logos) sont nettoyes apres traitement | MVP | DONE |
| 7.4 | La delegation de role utilise un verrou transactionnel pour eviter les corruptions | MVP | DONE |

---

## EPIC 8 : Retention & Engagement

> Les fonctionnalites qui font revenir les utilisateurs chaque jour.

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 8.1 | En tant qu'utilisateur, je peux sauvegarder ma commande habituelle pour un restaurant et la reutiliser en 1 clic | Phase 1c | TODO |
| 8.2 | En tant qu'utilisateur, je peux consulter l'historique de mes commandes passees (restos, plats, montants) | Phase 1c | TODO |
| 8.3 | En tant qu'utilisateur, je recois un rappel avant la deadline si je n'ai pas encore commande | Phase 1a | TODO |
| 8.4 | En tant qu'utilisateur, je vois un recap hebdomadaire dans le channel avec les stats de la semaine | Phase 1d | TODO |
| 8.5 | En tant qu'utilisateur, quand je commande pour la premiere fois, je vois un message de bienvenue dans le channel | Phase 1a | TODO |
| 8.6 | En tant qu'utilisateur, a la cloture je vois un recap enrichi (nb commandes, moyenne, runner mis en avant) | Phase 1d | TODO |

---

## EPIC 9 : Social & Discovery

> Transformer l'outil en rituel social et pousser la decouverte culinaire.

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 9.1 | En tant qu'utilisateur, chaque matin je vois des suggestions de restos basees sur l'historique avec rotation automatique | Phase 2 | TODO |
| 9.2 | En tant qu'utilisateur, je vois combien de fois chaque personne a ete runner ce mois et le bot suggere une rotation | Phase 2 | TODO |
| 9.3 | En tant qu'utilisateur, je peux debloquer des badges (Premiere commande, Explorateur, Runner confirme, Streak) | Phase 2 | TODO |
| 9.4 | En tant qu'utilisateur, apres une commande je peux noter le restaurant (pouce haut/bas) | Phase 2 | TODO |
| 9.5 | En tant qu'utilisateur, je vois un "Restaurant de la semaine" mis en avant dans le Morning Kick | Phase 2 | TODO |
| 9.6 | En tant qu'utilisateur, le bot me rappelle les remboursements en attente | Phase 2 | TODO |

---

## EPIC 10 : Closing & Payment

> La phase de cloture et de paiement. Faciliter le remboursement entre collegues apres une commande groupee.

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 10.1 | En tant que runner, apres cloture je peux envoyer un message DM individuel a chaque commandeur avec le montant exact a payer | Phase 1b | TODO |
| 10.2 | Le DM inclut automatiquement le numero de compte bancaire du runner (configurable dans son profil) | Phase 1b | TODO |
| 10.3 | Le DM inclut un QR code de virement bancaire (EPC QR Code / SCT) pre-rempli avec le montant et la reference | Phase 1b | TODO |
| 10.4 | En tant que commandeur, je peux cliquer "J'ai paye" dans le DM pour marquer ma dette comme reglee | Phase 1b | TODO |
| 10.5 | En tant que runner, je vois un tableau de suivi : qui a paye, qui n'a pas encore paye, montant restant | Phase 1b | TODO |
| 10.6 | En tant que runner, je peux relancer les personnes qui n'ont pas encore paye (bouton "Rappel") | Phase 1b | TODO |
| 10.7 | En tant qu'utilisateur, je peux configurer mon IBAN/compte bancaire dans mes preferences | Phase 1b | TODO |
| 10.8 | Balance courante : suivi des dettes cumulees entre utilisateurs avec compensation automatique | Phase 2 | TODO |

---

## EPIC 11 : Admin Dashboard & Configuration

> Un mini dashboard web (Laravel Blade) reserve aux admins pour configurer le bot par tenant. Tous les parametres actuellement dans config/lunch.php deviennent parametrables par tenant via ce dashboard.

| # | Story | Phase | Statut |
|---|-------|-------|--------|
| 11.1 | En tant qu'admin, je peux acceder a un mini dashboard web authentifie via Slack OAuth (Sign in with Slack) | Phase 1d | TODO |
| 11.2 | Les parametres de config/lunch.php (post_time, deadline_time, timezone, channel_id) sont migres en base de donnees et parametrables par tenant | Phase 1d | TODO |
| 11.3 | En tant qu'admin, je peux configurer les horaires de kickoff, deadline et fuseau horaire via le dashboard | Phase 1d | TODO |
| 11.4 | En tant qu'admin, je peux activer ou desactiver les features par tenant (rappel pre-deadline, message bienvenue, Friday Digest, Quick Run) | Phase 1d | TODO |
| 11.5 | En tant qu'admin, je peux personnaliser les messages par defaut du bot (kickoff, rappel, bienvenue, cloture) ou utiliser les messages standard | Phase 2 | TODO |
| 11.6 | En tant qu'admin, je peux creer des rappels recurrents personnalises avec jour, heure et message (ex: "tous les lundis a 10h15, rappel de commander chez Foodies") | Phase 2 | TODO |
| 11.7 | En tant qu'admin, je peux configurer le channel Slack cible et voir la liste des administrateurs du tenant | Phase 1d | TODO |
| 11.8 | En tant qu'admin, je peux configurer les coordonnees bancaires par defaut du tenant (IBAN, nom) pour les runners qui n'ont pas configure les leurs | Phase 2 | TODO |

---

## Synthese par phase

### MVP

| EPIC | Stories DONE | Stories TODO | Total |
|------|-------------|-------------|-------|
| 1 - Session Dejeuner | 12 | 0 | 12 |
| 2 - Catalogue Restaurants | 5 | 0 | 5 |
| 3 - Dashboard & Navigation | 7 | 0 | 7 |
| 4 - Messages & Notifications | 6 | 0 | 6 |
| 5 - Quick Run | 9 | 0 | 9 |
| 6 - Scheduler & Automatisation | 3 | 0 | 3 |
| 7 - Stabilite & Fiabilite | 4 | 0 | 4 |
| **Total MVP** | **46** | **0** | **46** |

**MVP 100% termine. 46 stories implementees et testees.**

### Phase 1a - Quick Wins Engagement

| EPIC | Stories TODO |
|------|-------------|
| 4 - Messages (rappel pre-deadline, bienvenue) | 2 |
| 6 - Scheduler (rappel pre-deadline) | 1 |
| 8 - Retention (rappel, bienvenue) | 2 |
| **Total Phase 1a** | **5** |

### Phase 1b - Closing & Payment

| EPIC | Stories TODO |
|------|-------------|
| 10 - Closing & Payment (DM, QR code, suivi) | 7 |
| **Total Phase 1b** | **7** |

### Phase 1c - Valeur Individuelle

| EPIC | Stories TODO |
|------|-------------|
| 3 - Dashboard (historique, favoris) | 2 |
| 8 - Retention (favoris, historique) | 2 |
| **Total Phase 1c** | **4** |

### Phase 1d - Visibilite Collective & Admin Setup

| EPIC | Stories TODO |
|------|-------------|
| 4 - Messages (digest, recap enrichi) | 2 |
| 6 - Scheduler (digest) | 1 |
| 8 - Retention (recap enrichi, digest) | 2 |
| 11 - Admin Dashboard (auth, config, horaires, toggles, channel) | 5 |
| **Total Phase 1d** | **10** |

### Phase 2 - Social & Discovery

| EPIC | Stories TODO |
|------|-------------|
| 2 - Catalogue (ratings, suggestions, resto de la semaine) | 3 |
| 9 - Social & Discovery | 6 |
| 10 - Closing & Payment (balance courante) | 1 |
| 11 - Admin Dashboard (messages custom, rappels recurrents, IBAN defaut) | 3 |
| **Total Phase 2** | **13** |

---

## Vue d'ensemble

```
MVP (46 DONE)
  ├── Session Dejeuner ██████████████████████████ 12/12
  ├── Catalogue         ██████████████████████████ 5/5
  ├── Dashboard         ██████████████████████████ 7/7
  ├── Messages          ██████████████████████████ 6/6
  ├── Quick Run         ██████████████████████████ 9/9
  ├── Scheduler         ██████████████████████████ 3/3
  └── Stabilite         ██████████████████████████ 4/4

Phase 1a - Quick Wins Engagement (5 TODO)
  ├── Rappel pre-deadline
  └── Message de bienvenue

Phase 1b - Closing & Payment (7 TODO)
  ├── DM de paiement au commandeur
  ├── QR code virement bancaire
  ├── Suivi des paiements
  ├── Relance impaye
  └── Configuration IBAN

Phase 1c - Valeur Individuelle (4 TODO)
  ├── Historique personnel
  └── Favoris / Reorder

Phase 1d - Visibilite Collective & Admin Setup (10 TODO)
  ├── Friday Digest
  ├── Closing recap enrichi
  ├── Mini dashboard web (auth Slack OAuth)
  ├── Config par tenant (horaires, deadline, timezone)
  ├── Feature toggles par tenant
  └── Gestion channel et admins

Phase 2 - Social & Discovery (13 TODO)
  ├── Morning Kick intelligent
  ├── Rotation runner
  ├── Badges
  ├── Ratings
  ├── Restaurant de la semaine
  ├── Balance de remboursement
  ├── Messages personnalisables par tenant
  ├── Rappels recurrents personnalises
  └── IBAN par defaut tenant
```

**Le MVP est 100% termine. Les 39 stories restantes sont reparties entre la Phase 1a (Quick Wins, 5 stories), Phase 1b (Closing & Payment, 7 stories), Phase 1c (Valeur Individuelle, 4 stories), Phase 1d (Visibilite Collective & Admin Setup, 10 stories) et la Phase 2 (Social & Discovery, 13 stories).**