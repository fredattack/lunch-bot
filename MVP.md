# MVP - Lunch Bot

**Date** : 15 fevrier 2026
**Objectif** : Definir le perimetre exact du MVP presentable aux beta-testeurs et aux decideurs, puis les deux phases d'amelioration suivantes.

---

## Vision produit

Lunch Bot est le bot Slack qui elimine le chaos de la coordination dejeuner en entreprise. Il remplace les messages eparpilles, les listes papier et les tableurs par un flow structure, trace et visible, directement dans Slack.

**Le MVP doit demontrer** :
1. Que le bot simplifie concretement la coordination (session dejeuner structuree)
2. Qu'il couvre aussi le cas spontane ("je vais chez X, quelqu'un veut quelque chose ?")
3. Que l'experience est fluide et sans friction pour tous les profils (organisateur, suiveur, runner)

---

## Perimetre MVP

### Ce qui est IN

#### Session Dejeuner (le coeur)

| Fonctionnalite | Description | Statut |
|----------------|-------------|--------|
| Kickoff automatique | Le bot poste chaque matin dans le channel a l'heure configuree | DONE |
| Verrouillage automatique | Les sessions sont verrouillees a la deadline, chaque minute | DONE |
| Proposer un resto (catalogue) | Choisir un restaurant existant, fixer une deadline, ajouter une note | DONE |
| Proposer un nouveau resto | Creer un restaurant a la volee avec nom, URL, type, logo | DONE |
| Commander | Saisir sa commande avec description et prix estime | DONE |
| Modifier sa commande | Changer le contenu ou le prix avant la deadline | DONE |
| Supprimer sa commande | Retirer sa commande avec confirmation | DONE |
| Se porter volontaire runner | Cliquer pour devenir runner/orderer d'une proposition | DONE |
| Deleguer le role | Transferer le role de runner a un autre collegue | DONE |
| Consulter le recap | Le runner voit toutes les commandes avec les details | DONE |
| Ajuster les prix finaux | Le runner saisit les prix reels apres l'achat | DONE |
| Cloturer une proposition | Le runner ferme la commande | DONE |
| Cloturer la session | Fermer la journee avec recap des montants dus | DONE |
| Dashboard /lunch | 6 etats contextuels selon la situation de l'utilisateur | DONE |

#### Catalogue Restaurants

| Fonctionnalite | Description | Statut |
|----------------|-------------|--------|
| Liste des restaurants | Voir tous les restos actifs avec recherche | DONE |
| Ajouter un restaurant | Via le flow de proposition ou via le catalogue | DONE |
| Modifier un restaurant | Nom, cuisine, URLs, types de livraison, logo | DONE |
| Logo et menu | Upload de fichiers avec generation de miniature | DONE |

#### Messages Channel

| Fonctionnalite | Description | Statut |
|----------------|-------------|--------|
| Message de kickoff | Post automatique quotidien avec boutons d'action | DONE |
| Message de proposition | Annonce quand une commande est lancee | DONE |
| Mise a jour en direct | Le message se met a jour avec le nombre de commandes | DONE |
| Notification de verrouillage | "Commandes verrouillees" a la deadline | DONE |
| Annonce de delegation | "Role transfere de @X a @Y" | DONE |
| Recap de cloture | Montants dus par personne a la fermeture | DONE |

#### Quick Run (nouveau)

| Fonctionnalite | Description | Statut |
|----------------|-------------|--------|
| Lancer un Quick Run | Annoncer "Je vais chez X" avec un delai | TODO |
| Ajouter une demande | Un collegue ajoute ce qu'il veut (texte + prix estime) | TODO |
| Message channel avec bouton | Post visible avec compteur de demandes et CTA | TODO |
| Verrouiller les demandes | Le runner clique "Je pars", plus de nouvelles demandes | TODO |
| Recap pour le runner | Liste de ce qu'il doit acheter | TODO |
| Cloturer le run | Ajuster les prix reels et poster le recap final | TODO |

#### Stabilite MVP

| Fonctionnalite | Description | Statut |
|----------------|-------------|--------|
| Retirer les outils dev en prod | Supprimer DevResetDatabase hors environnement local | TODO |
| Retry API Slack | Reessai automatique en cas d'echec ou rate limit | TODO |

### Ce qui est OUT (pas dans le MVP)

| Fonctionnalite | Raison |
|----------------|--------|
| Favoris / reorder rapide | Phase 1 - Retention |
| Historique personnel ("Mes commandes") | Phase 1 - Retention |
| Rappel pre-deadline | Phase 1 - Retention |
| Friday Digest / recap hebdomadaire | Phase 1 - Retention |
| Closing recap enrichi (stats, records) | Phase 1 - Retention |
| Morning Kick intelligent (suggestions) | Phase 2 - Social |
| Rotation runner | Phase 2 - Social |
| Badges / gamification | Phase 2 - Social |
| Rating des restaurants | Phase 2 - Social |
| Balance de remboursement | Phase 2 - Social |
| Dashboard web admin | Hors scope |
| Multi-provider (Teams/Discord) | Hors scope |
| Integration paiement | Hors scope |
| Export comptable | Hors scope |

---

## Phase 1 : Retention & Engagement

> **Objectif** : Creer les boucles qui font revenir les utilisateurs chaque jour. Se lance apres validation du MVP en beta.

| Fonctionnalite | Description | Impact attendu |
|----------------|-------------|----------------|
| Favoris / reorder | Sauvegarder "ma commande habituelle" pour recommander en 1 clic | Friction → 0, retention +30% |
| Historique personnel | Voir mes commandes passees, mes restos, mes depenses | Raison de revenir, valeur personnelle |
| Rappel pre-deadline | "Plus que 10 min, 6 collegues ont deja commande" | +20% de commandes estimees |
| Friday Digest | Recap hebdomadaire dans le channel (stats, top restos, MVP runner) | Viralite, ancrage, reconnaissance |
| Closing recap enrichi | Stats fun apres cloture (nombre de commandes, moyenne, runner mis en avant) | Satisfaction, reconnaissance |
| Message de bienvenue | "Bienvenue @Sarah ! 12 collegues utilisent deja Lunch Bot" | Onboarding, viralite |

**Critere de passage** : Le MVP est deploye, les beta-testeurs l'utilisent quotidiennement, les KPIs de base sont mesurables.

---

## Phase 2 : Social & Discovery

> **Objectif** : Transformer l'outil en rituel social et pousser la decouverte culinaire. Se lance quand la retention J7 depasse 50%.

| Fonctionnalite | Description | Impact attendu |
|----------------|-------------|----------------|
| Morning Kick intelligent | Suggestions de restos basees sur l'historique, rotation automatique | Resout le cold start |
| Tracking runner + rotation | Compteur de runs par personne, suggestion de tour | Equite, resout la fatigue runner |
| Badges de base | Premiere commande, Explorateur, Runner confirme, Streak | Gamification legere |
| Rating post-commande | Pouce haut/bas sur les restos apres cloture | Data qualitative, decouverte |
| Restaurant de la semaine | Mise en avant d'un resto peu commande | Decouverte, casser la routine |
| Balance de remboursement | Suivi des dettes entre collegues avec compensation | Resout la friction paiement |

**Critere de passage** : La retention J7 est stable au-dessus de 50%, au moins 5 commandes/jour en moyenne.

---

## Criteres de succes MVP

| Metrique | Cible | Comment mesurer |
|----------|-------|-----------------|
| Premiere session complete | Jour 1 | 1 session avec au moins 3 commandes et une cloture |
| Adoption initiale | Semaine 1 | 50% du groupe pilote a commande au moins 1 fois |
| Usage regulier | Semaine 2 | Au moins 1 session active 4 jours sur 5 |
| Premier Quick Run | Semaine 1 | Au moins 1 quick run realise et cloture |
| Satisfaction qualitative | Semaine 2 | Feedback positif des beta-testeurs ("c'est plus simple qu'avant") |

---

## Public cible beta

- **Taille** : 1-2 equipes de 10-15 personnes
- **Profil** : Equipes qui commandent deja regulierement ensemble (le probleme est connu)
- **Champions** : 2-3 personnes qui organisent deja les dejeuners (les convertir en premiers utilisateurs)
- **Environnement** : Workspace Slack actif avec un channel dedie ou general

---

## Pitch beta (1 paragraphe)

> Lunch Bot organise vos dejeuners d'equipe directement dans Slack. Proposez un resto, vos collegues commandent en 2 clics, le runner voit le recap et cloture avec les prix reels. Plus de messages perdus, plus de listes papier, plus de "tu me devais combien ?". Et quand quelqu'un va faire une course, il peut lancer un Quick Run pour que les collegues ajoutent leurs demandes en 5 minutes. Simple, rapide, dans Slack.
