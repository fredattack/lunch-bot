# Lunch Bot — Document de Présentation Projet

**Date** : 15 février 2026
**Destinataires** : Direction des Ressources Humaines, Direction Générale (CEO), Project Manager Lead
**Classification** : Document interne — Confidentiel

---

## 1. Résumé Exécutif

**Lunch Bot** est un outil de coordination des commandes de repas en équipe, intégré nativement dans Slack. Il remplace les messages éparpillés, les listes papier et les tableurs par un workflow structuré, tracé et visible, directement dans l'environnement de travail des collaborateurs.

> *"Plus jamais de galère pour organiser le déjeuner. En 2 clics, tout le monde a commandé."*

### Le problème adressé

Chaque jour dans l'entreprise, la même scène se répète :

- *"Quelqu'un commande chez le snack ?"*
- *"C'est qui qui va chercher ? C'est pas moi, j'y suis allé hier..."*
- *"Thomas tu me devais combien déjà ?"*

Le déjeuner en entreprise génère **5 types de friction** : coordination, responsabilité, communication, comptabilité et charge mentale. Lunch Bot élimine ces frictions en centralisant l'ensemble du processus dans un seul outil.

### La solution

Un bot Slack qui permet de :
- **Proposer** un restaurant et collecter les commandes de l'équipe
- **Organiser** les rôles (qui commande, qui va chercher)
- **Suivre** les prix estimés et réels
- **Clôturer** la session avec un récapitulatif des montants dus
- Le tout **sans quitter Slack**, sans formation, sans installation

---

## 2. Valeur pour l'Entreprise

### 2.1 Pour la Direction Générale (CEO)

| Dimension | Impact |
|-----------|--------|
| **Productivité** | Estimation de 15-20 min/jour économisées par équipe vs coordination manuelle |
| **Coût de développement** | Projet interne, stack technique maîtrisée, pas de licence externe |
| **Scalabilité** | Architecture multi-tenant dès le départ — déployable sur plusieurs équipes/entités sans refonte |
| **Potentiel commercial** | Modèle SaaS possible à terme (freemium + plans payants), marché non structuré |
| **Innovation interne** | Démontre la capacité de l'entreprise à résoudre ses propres problèmes avec des outils sur mesure |

**ROI estimé** : Pour une équipe de 15 personnes commandant ensemble 4 jours/semaine, le gain de temps représente environ **60 heures/an** de productivité récupérée, sans compter la réduction de la charge mentale et l'amélioration de la cohésion d'équipe.

### 2.2 Pour la Direction des Ressources Humaines (DRH)

| Dimension | Impact |
|-----------|--------|
| **Cohésion d'équipe** | Le déjeuner est le premier moment social de la journée — le bot le structure et l'amplifie |
| **Inclusion** | Tous les collaborateurs ont accès aux mêmes informations au même moment, pas de "cercle informel" |
| **Équité** | Système de rotation des runners prévu pour éviter que ce soit toujours les mêmes qui s'en chargent |
| **Onboarding** | Les nouveaux arrivants sont intégrés naturellement dans le rituel déjeuner dès le jour 1 |
| **Bien-être au travail** | Réduction de la charge mentale liée à l'organisation quotidienne des repas |
| **Marque employeur** | Outil innovant, moderne, intégré dans les habitudes de travail — valorisable en communication interne |

**Cas d'usage concret — L'intégration d'un nouveau collaborateur** :
> Sarah arrive lundi. À 11h, elle voit le message du bot dans le channel. Elle clique, commande en 30 secondes, et déjeune avec l'équipe. Pas besoin de demander "vous faites comment pour le déjeuner ?". Le bot a déjà répondu.

### 2.3 Pour le Project Manager Lead

| Dimension | Détail |
|-----------|--------|
| **Maturité technique** | Architecture solide, multi-tenancy, séparation des couches, 280+ tests automatisés |
| **État d'avancement** | MVP à **78% terminé** — 36 stories DONE sur 46. Ne reste que le Quick Run (10 stories) |
| **Qualité** | 280+ tests, 0 échecs, formatage de code automatisé |
| **Roadmap** | 3 phases clairement définies avec critères de passage objectifs |
| **Stabilité & sécurité** | Tous les prérequis prod sont couverts (retry API, nettoyage fichiers, protection dev tools, verrous transactionnels) |
| **Équipe requise** | 1 développeur fullstack pour la finalisation et le maintien |

---

## 3. Fonctionnalités Clés du MVP

### 3.1 Session Déjeuner — Le Coeur du Produit

```
[11:00] Le bot poste automatiquement le kickoff dans le channel
    │
    ├── Un collaborateur propose un restaurant
    │       → Le bot annonce la proposition dans le channel
    │
    ├── Les collègues commandent via un modal simple
    │       → Le message se met à jour en temps réel (compteur)
    │
    ├── Un volontaire se désigne comme runner
    │       → Il peut déléguer si besoin
    │
    ├── À la deadline, les commandes sont verrouillées
    │       → Le runner consulte le récapitulatif
    │
    ├── Le runner ajuste les prix réels après l'achat
    │
    └── Clôture : le bot affiche qui doit combien à qui
```

### 3.2 Tableau des fonctionnalités livrées

| Module | Fonctionnalités | Statut |
|--------|----------------|--------|
| **Session Déjeuner** | Kickoff auto, propositions, commandes, modifications, suppressions, rôles, délégation, récap, ajustement prix, clôture | 12/12 DONE |
| **Catalogue Restaurants** | Liste, ajout, modification, upload logo/menu, recherche | 5/5 DONE |
| **Dashboard /lunch** | 6 états contextuels selon la situation de l'utilisateur | 7/7 DONE |
| **Messages Channel** | Kickoff, proposition, mise à jour temps réel, verrouillage, délégation, récap clôture | 6/6 DONE |
| **Stabilité & Sécurité** | Protection dev tools, retry API, nettoyage fichiers, verrous transactionnels | 4/4 DONE |
| **Scheduler** | Kickoff automatique, verrouillage deadline | 2/3 DONE |
| **Quick Run** | "Je vais chez X, quelqu'un veut quelque chose ?" | 0/9 — Prochaine étape |

**Bilan : 36 stories terminées sur 46 (78%). Seul le module Quick Run reste à implémenter.**

### 3.3 Le Dashboard Contextuel

La commande `/lunch` offre 6 vues différentes selon la situation de l'utilisateur :

| État | Ce que voit l'utilisateur |
|------|--------------------------|
| Aucune proposition | Bouton pour proposer un restaurant |
| Propositions en cours, pas encore commandé | Liste des propositions avec bouton "Commander" |
| A commandé | Sa commande avec option de modification |
| Est runner | Outils de gestion (récap, clôture, délégation) |
| Tout est clôturé | Résumé de la journée |
| Jour passé | Historique en lecture seule |

---

## 4. Garanties Techniques

Le projet a été conçu avec des principes qui garantissent sa pérennité et son évolutivité :

- **Indépendant de Slack** : Le coeur de l'application ne dépend pas de Slack. L'ajout d'un autre canal (Microsoft Teams, Discord) est possible sans réécriture.
- **Multi-tenant natif** : Chaque entreprise/workspace a ses données isolées. Le déploiement multi-équipes ou multi-entreprises est prévu dès le départ.
- **Qualité logicielle** : 280+ tests automatisés couvrent l'ensemble des fonctionnalités. Le code est formaté et vérifié automatiquement.
- **Stabilité en production** : Retry automatique sur les appels API, nettoyage des fichiers temporaires, protection des outils d'administration, et verrouillage transactionnel pour éviter les conflits de données.
- **Prêt pour la montée en charge** : L'architecture permet une migration vers une base de données plus robuste si le nombre d'utilisateurs le justifie.

---

## 5. Roadmap Produit

### 5.1 Vue d'ensemble des phases

```
         MVP                    Phase 1                 Phase 2
    ┌────────────┐        ┌──────────────┐        ┌──────────────┐
    │  Session    │        │  Rétention & │        │  Social &    │
    │  Déjeuner   │───────▶│  Engagement  │───────▶│  Découverte  │
    │  + Quick Run│        │              │        │              │
    └────────────┘        └──────────────┘        └──────────────┘
     78% terminé           14 stories              10 stories
     10 stories restantes  Critère: usage          Critère: rétention
                           quotidien validé        J7 > 50%
```

### 5.2 MVP — Finalisation (estimé : 1-2 semaines)

Le coeur du produit (sessions, commandes, dashboard, catalogue, stabilité) est **100% terminé**.
Il ne reste qu'un seul chantier :

| Chantier | Stories | Priorité |
|----------|---------|----------|
| Quick Run ("Je vais chez X") | 9 stories | Haute — use case quotidien non couvert |
| Rappel pré-deadline | 1 story | Moyenne — améliore le taux de commandes |

### 5.3 Phase 1 — Rétention & Engagement (post-validation MVP)

> Objectif : Créer les boucles qui font revenir les utilisateurs chaque jour.

| Fonctionnalité | Impact attendu |
|----------------|----------------|
| Favoris / commande en 1 clic | Friction réduite à zéro, rétention +30% |
| Historique personnel | Valeur individuelle, raison de revenir |
| Rappel pré-deadline | +20% de commandes estimées |
| Récap hebdomadaire (Friday Digest) | Viralité, ancrage, reconnaissance |
| Message de bienvenue | Onboarding naturel |

**Critère de passage vers Phase 2** : Les bêta-testeurs utilisent le bot quotidiennement, KPIs de base mesurables.

### 5.4 Phase 2 — Social & Découverte

> Objectif : Transformer l'outil en rituel social et pousser la découverte culinaire.

| Fonctionnalité | Impact attendu |
|----------------|----------------|
| Suggestions de restaurants intelligentes | Résout le "cold start" quotidien |
| Rotation runner automatique | Équité, réduit la fatigue du runner |
| Badges et reconnaissance | Gamification légère, engagement |
| Rating des restaurants | Données qualitatives, découverte |
| Balance de remboursement | Résout la friction post-commande |

---

## 6. Stratégie de Déploiement

### 6.1 Plan de lancement en 4 semaines

| Semaine | Objectif | KPI cible |
|---------|----------|-----------|
| **S1 — Prouver la valeur** | 2-3 champions identifiés, formation en 5 min | 1 session complète réussie, 3-5 commandes/jour |
| **S2 — Élargir le cercle** | Les champions évangélisent via l'usage visible | 50% de l'équipe a commandé au moins 1 fois |
| **S3 — Créer l'habitude** | Le bot devient le canal par défaut | 70% des jours ouvrés ont une session active |
| **S4 — Ancrer le rituel** | "On faisait comment avant ?" | Rétention hebdomadaire > 80% |

### 6.2 Public cible bêta

- **Taille** : 1-2 équipes de 10-15 personnes
- **Profil** : Équipes qui commandent déjà régulièrement ensemble (le problème est connu et vécu)
- **Champions** : 2-3 personnes qui organisent déjà les déjeuners naturellement
- **Environnement** : Workspace Slack actif avec un channel dédié

### 6.3 Critères de succès mesurables

| Métrique | Cible bêta | Cible 3 mois |
|----------|-----------|--------------|
| Utilisateurs actifs quotidiens / équipe | 5+ | 10+ |
| Commandes / jour | 5+ | 15+ |
| Taux de complétion des sessions | 70% | 90% |
| Rétention J7 | 50% | 70% |
| Temps avant première commande | < 2 min | < 1 min |
| NPS (Net Promoter Score) | > 30 | > 50 |

---

## 7. Analyse des Risques

### 7.1 Risques techniques

| Risque | Probabilité | Impact | Mitigation |
|--------|-------------|--------|------------|
| Dépendance à l'API Slack (indisponibilité) | Faible | Élevé | Mécanisme de retry en place + architecture découplée |
| Performance avec montée en charge | Faible | Moyen | Architecture prévue pour migration vers une base plus robuste |

### 7.2 Risques produit

| Risque | Probabilité | Impact | Mitigation |
|--------|-------------|--------|------------|
| Adoption insuffisante | Moyenne | Élevé | Stratégie de champions + viralité naturelle du bot |
| Fatigue du runner (toujours les mêmes) | Élevée | Moyen | Rotation automatique prévue en Phase 2 |
| Lassitude (toujours les mêmes restaurants) | Moyenne | Moyen | Suggestions intelligentes prévues en Phase 2 |
| Friction de remboursement post-commande | Élevée | Moyen | Balance de remboursement prévue en Phase 2 |

### 7.3 Risques organisationnels

| Risque | Probabilité | Impact | Mitigation |
|--------|-------------|--------|------------|
| Résistance au changement | Moyenne | Moyen | Le bot ne remplace pas, il structure l'existant |
| Manque de sponsor interne | Faible | Élevé | Ce document vise à obtenir le sponsoring direction |
| Charge de maintenance | Faible | Faible | Architecture propre, tests automatisés, code documenté |

---

## 8. Vision à Long Terme

### 8.1 Potentiel d'évolution

```
Aujourd'hui              6 mois                  12 mois
┌─────────────┐    ┌─────────────────┐    ┌──────────────────┐
│ Bot Slack    │    │ Plateforme      │    │ Standard         │
│ 1 équipe    │───▶│ multi-équipes   │───▶│ entreprise       │
│ Coordination│    │ Rétention +     │    │ Multi-provider + │
│ de base     │    │ Social          │    │ Analytics + SaaS │
└─────────────┘    └─────────────────┘    └──────────────────┘
```

### 8.2 Potentiel de monétisation (vision future)

> La monétisation n'est pas la priorité immédiate. L'objectif premier est de valider le Product-Market Fit.

| Plan | Prix | Inclus |
|------|------|--------|
| **Gratuit** | 0 EUR | Jusqu'à 15 utilisateurs, session + quick run, historique 30 jours |
| **Pro** | 9 EUR/mois/équipe | Utilisateurs illimités, analytics, historique illimité, gamification |
| **Enterprise** | Sur devis | Multi-workspace, dashboard manager, export comptable, support prioritaire |

### 8.3 Avantages concurrentiels durables

| Mois | Avantage acquis |
|------|----------------|
| Mois 1 | **Intégration** — Natif Slack, zéro friction d'installation |
| Mois 2-3 | **Data** — Historique, préférences, habitudes accumulées |
| Mois 4-6 | **Réseau** — Adoption équipe, norme sociale établie |
| Mois 6+ | **Habitude** — Rituel quotidien ancré, point de non-retour |

---

## 9. Demande et Prochaines Étapes

### 9.1 Ce que nous demandons

| Demande | Destinataire | Détail |
|---------|-------------|--------|
| **Validation du projet** | CEO | Accord pour finaliser le MVP et lancer la bêta |
| **Identification du groupe pilote** | DRH | Sélection de 1-2 équipes de 10-15 personnes pour la bêta |
| **Allocation ressource** | PM Lead | 1 développeur pendant 1-2 semaines pour finaliser le Quick Run |
| **Sponsoring interne** | DRH + CEO | Communication interne pour légitimer l'initiative |

### 9.2 Calendrier prévisionnel

| Phase | Durée estimée | Livrable |
|-------|--------------|----------|
| Finalisation MVP (Quick Run) | 1-2 semaines | MVP complet et sécurisé |
| Déploiement bêta | 1 semaine | Bot opérationnel sur le workspace pilote |
| Observation et itération | 4 semaines | Rapport d'usage avec KPIs |
| Décision Go/No-Go Phase 1 | Fin de bêta | Sur base des métriques de rétention |

### 9.3 Le test de réussite

Le produit aura réussi quand quelqu'un dans l'équipe dira naturellement :

> *"Attends, passe par le bot pour qu'on sache qui veut quoi."*

À ce moment-là, le bot sera devenu le standard.

---

## Annexes

| Document | Emplacement | Description |
|----------|-------------|-------------|
| Contexte et philosophie du projet | `Docs/project/meta-contexte.md` | Vision, philosophie et invariants |
| Périmètre MVP détaillé | `Docs/project/mvp.md` | Stories IN/OUT avec statuts |
| Roadmap complète | `Docs/project/roadmap.md` | EPICs et stories par phase |
| Audit business et product marketing | `Docs/reviews/audit-business.md` | Analyse concurrentielle et stratégie d'adoption |
