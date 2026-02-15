# Audit Business & Product Marketing - Lunch Bot

**Date** : 15 fevrier 2026
**Objectif** : Identifier les leviers d'adoption et construire la roadmap business pour faire de Lunch Bot le standard de commande dejeuner en entreprise.

> Cet audit est exclusivement business et produit. Les aspects techniques sont traites dans `AUDIT.md`.

---

## 1. DIAGNOSTIC PRODUIT

### 1.1 Le probleme reel

Chaque jour dans des milliers d'entreprises, la meme scene se repete :

> "Quelqu'un commande chez le snack ?"
> "C'est qui qui va chercher ? C'est pas moi, j'y suis alle hier..."
> "Thomas tu me devais combien deja ?"
> "Je vais chez Delhaize, quelqu'un veut quelque chose ?"

Le dejeuner en entreprise genere **5 types de friction** :
1. **Coordination** - Savoir qui veut quoi, ou, et quand
2. **Responsabilite** - Trouver le volontaire qui va chercher / commander
3. **Communication** - Informer tout le monde, ne oublier personne
4. **Comptabilite** - Qui doit quoi a qui
5. **Charge mentale** - C'est toujours le meme qui s'occupe de tout

### 1.2 Proposition de valeur

*"Plus jamais de galere pour organiser le dejeuner. En 2 clics, tout le monde a commande."*

Lunch Bot centralise la coordination des repas d'equipe directement dans Slack. Il elimine les messages eparpilles, le papier et le tableur, et remplace ca par un flow structure, trace et visible.

### 1.3 Ce que le produit fait bien

- Proposer un restaurant et collecter les commandes
- Assigner un runner/orderer avec gestion des roles
- Suivre les commandes avec prix estimes et prix finaux
- Cloturer une session avec recap des montants
- Gerer un catalogue de restaurants reutilisable
- Tout ca sans quitter Slack

### 1.4 Positionnement

| Dimension | Actuel | Cible |
|-----------|--------|-------|
| Perception | "Un bot Slack pour les commandes" | "Le rituel dejeuner de l'equipe" |
| Frequence d'usage | Quand quelqu'un y pense | Automatique, chaque jour |
| Engagement | Transactionnel (commande/ferme) | Social (participe/reagit/decouvre) |
| Donnees generees | Commandes brutes | Intelligence collective food |
| Valeur percue | Pratique | Indispensable |
| Stickiness | Faible (remplacable par un message Slack) | Forte (habitudes + historique + social) |

---

## 2. ANALYSE DU PARCOURS UTILISATEUR

### 2.1 Carte d'experience actuelle

```
[11:00] Scheduler poste le kickoff
   │
   ├─ Friction #1 : Si desactive, quelqu'un doit penser a lancer /lunch
   │
[Proposition] User ouvre /lunch, propose un resto
   │
   ├─ Friction #2 : Doit connaitre les restos, pas de suggestions
   │
[Commande] Les collegues voient le message, commandent
   │
   ├─ Friction #3 : Message channel facile a rater dans le flux Slack
   │
[Roles] Quelqu'un se designe runner/orderer
   │
   ├─ Friction #4 : Personne ne veut etre runner, pas de rotation
   │
[Cloture] Le runner ajuste les prix, cloture
   │
   ├─ Friction #5 : Saisie manuelle des prix finaux, un par un
   │
[Remboursement] Affichage des totaux
   │
   └─ Friction #6 : Aucun mecanisme de paiement, juste un affichage
```

### 2.2 Moments de verite

**Moment #1 - La premiere utilisation** (make or break)
- Actuellement : Dashboard vide, l'utilisateur doit deviner quoi faire
- Ideal : Onboarding guide, suggestions de restos, premier ordre en 30 secondes

**Moment #2 - "Qui va chercher ?"** (le point de douleur social)
- Actuellement : Personne ne clique, attente passive
- Ideal : Rotation automatique, systeme de volontariat avec reconnaissance

**Moment #3 - L'heure du bilan** (satisfaction ou frustration)
- Actuellement : Liste brute des montants
- Ideal : Recapitulatif engageant avec stats et reconnaissance du runner

### 2.3 Funnel d'engagement estime

```
100% ──── Voient le message channel           ← Impression
 60% ──── Ouvrent /lunch                      ← Interet
 40% ──── Regardent les propositions          ← Consideration
 25% ──── Passent une commande                ← Conversion
 15% ──── Reviennent le lendemain             ← Retention
  5% ──── Proposent un resto eux-memes        ← Activation
  2% ──── Se portent volontaires runner       ← Power user
```

**Levier principal** : Passer de 15% a 50%+ de retention jour-sur-jour.

---

## 3. FAILLES PRODUIT

### 3.1 Faille #1 : Le Cold Start Problem

**Symptome** : Si personne ne propose de resto, le bot reste silencieux. Jour mort.

**Impact** : Un jour sans activite cree un precedent. "On n'utilise plus le bot." L'adoption s'effondre.

**Pistes** :
- Suggestion automatique de 2-3 restos populaires chaque matin (base sur l'historique)
- Rotation : ne pas proposer le meme 3 jours de suite
- Quick-start : "La derniere fois, 8 personnes ont commande chez Sushi Wasabi. On relance ?"
- Sondage eclair : "Envie de quoi aujourd'hui ?" avec 3-4 options rapides

### 3.2 Faille #2 : Le "Quick Run" n'est pas couvert

**Symptome** : Le message "Je vais chez Delhaize, quelqu'un veut quelque chose ?" ou "Je passe chez Laurent Dumont, je ramene un sandwich a quelqu'un ?" se produit quotidiennement dans les channels Slack. Le bot ne capture pas ce comportement.

**Impact** : Le bot est absent d'un use case ultra-frequent. Des interactions quotidiennes echappent au produit.

**Le concept** : C'est le meme principe qu'une session dejeuner, sauf que :
- Le runner est connu des le depart (c'est celui qui annonce qu'il sort)
- Le runner ne commande pas forcement pour lui-meme
- La deadline est courte (5-15 min, le temps que la personne parte)
- Le lieu n'est pas forcement un restaurant (supermarche, boulangerie, cafe...)
- Les commandes sont simples (un article, pas un plat complet)

**Exemples concrets** :
- "Je vais chez Delhaize, quelqu'un veut quelque chose ?" → courses supermarche
- "Je passe chez Laurent Dumont, je ramene des sandwichs ?" → snack du midi
- "Je descends chercher des cafes, qui en veut ?" → pause cafe

**Piste de flow** :
1. L'utilisateur annonce qu'il va quelque part (lieu + delai)
2. Le bot poste dans le channel avec un bouton pour ajouter une demande
3. Les collegues ajoutent ce qu'ils veulent (texte libre + prix estime)
4. L'utilisateur part → les demandes sont verrouillees
5. Au retour, il ajuste les prix si besoin et cloture
6. Recap des montants dus

### 3.3 Faille #3 : Zero mecanisme de retention

**Symptome** : Rien ne ramene l'utilisateur le lendemain. Pas de streak, pas de rappel, pas de FOMO.

**Impact** : L'usage depend de la volonte individuelle. Pas de boucle d'engagement.

**Pistes** :
- Rappel pre-deadline ("Plus que 10 min ! 6 collegues ont deja commande")
- FOMO social ("Sarah, Thomas et 4 autres ont commande chez Pizza Mario")
- Favoris / "Ma commande habituelle" pour reduire la friction a zero
- Recap quotidien et hebdomadaire

### 3.4 Faille #4 : Le probleme du Runner non resolu

**Symptome** : C'est toujours le meme qui va chercher. Pas de rotation, pas d'incentive, pas de reconnaissance.

**Impact** : Le runner regulier finit par en avoir marre. Il decroche, tout le groupe suit.

**Pistes** :
- Tracking du nombre de runs par personne (visible)
- Suggestion de rotation ("C'est au tour de @Marie, derniere fois il y a 12 jours")
- Reconnaissance dans les recaps ("MVP Runner de la semaine")
- Opt-out respectueux (se declarer indisponible)

### 3.5 Faille #5 : Le remboursement reste un cauchemar

**Symptome** : Le bot affiche les montants mais ne resout pas le probleme de qui doit quoi a qui.

**Impact** : La friction post-commande nuit a l'experience globale. "J'ai pas ete rembourse de la semaine derniere."

**Pistes** :
- Balance courante entre utilisateurs
- Compensation automatique ("Thomas te devait 12.50, tu lui dois 8.00, solde : 4.50")
- Rappels de remboursement
- Bilan hebdomadaire des depenses

### 3.6 Faille #6 : Aucune decouverte culinaire

**Symptome** : Les equipes commandent toujours aux memes 3 restos. La routine tue l'engagement.

**Impact** : La lassitude s'installe. Le bot perd son interet.

**Pistes** :
- Restaurant de la semaine
- Catalogue enrichi (types de cuisine, fourchette de prix, avis)
- Badge "Explorateur" pour ceux qui testent de nouveaux restos

---

## 4. STRATEGIES DE CROISSANCE ET VIRALITE

### 4.1 Boucle de croissance organique

```
Utilisateur commande ──→ Message visible dans le channel
        ↑                         │
        │                         ▼
  S'engage plus          Collegue curieux clique
        ↑                         │
        │                         ▼
  Valeur confirmee       Decouvre le bot, commande
        ↑                         │
        └─────────────────────────┘
```

**Levier cle** : Chaque commande est un acte social visible. Plus le message est engageant, plus il genere de curiosite.

### 4.2 Effets de reseau internes

| Metrique | Seuil | Effet |
|----------|-------|-------|
| 3 commandes/jour | Minimum viable | Le bot "fonctionne" mais reste optionnel |
| 5-7 commandes/jour | Point de bascule | Le bot devient la norme de facto |
| 10+ commandes/jour | Masse critique | Ne PAS utiliser le bot = etre exclu |
| 15+ commandes/jour | Standard | Le bot est l'infrastructure dejeuner |

**Objectif** : Atteindre 5-7 commandes/jour dans les 2 premieres semaines.

### 4.3 Viral hooks

**Hook #1 - Le message de bienvenue**
Quand un nouvel utilisateur commande pour la premiere fois :
> "Bienvenue @Sarah ! C'est sa premiere commande. 12 collegues utilisent deja Lunch Bot."

**Hook #2 - Le milestone collectif**
> "100eme commande de l'equipe ! Vous avez commande chez 14 restaurants differents."

**Hook #3 - Le recap hebdomadaire**
Chaque vendredi :
> "Cette semaine : 34 commandes, 5 restaurants. MVP Runner : Thomas. Restaurant prefere : Sushi Wasabi."

**Hook #4 - L'invitation naturelle**
Chaque message de commande ou de quick run est une invitation publique a interagir. Le bot se vend tout seul a chaque utilisation.

---

## 5. FONCTIONNALITES A HAUTE VALEUR (Impact vs Effort)

### JACKPOT (Impact eleve, Effort modere)

| Feature | Impact | Pourquoi |
|---------|--------|----------|
| Scheduler automatique + rappels | Critique | Coeur du produit - sans ca, rien ne fonctionne automatiquement |
| Quick Run ("Je vais chez X") | Tres haut | Capture un use case quotidien non couvert |
| Favoris / Reorder rapide | Tres haut | Reduit la friction a quasi-zero |
| Historique personnel | Tres haut | Valeur personnelle, raison de revenir |
| Recap hebdomadaire | Tres haut | Viralite + retention + visibilite |

### QUICK WINS (Impact modere, Effort faible)

| Feature | Impact | Pourquoi |
|---------|--------|----------|
| Rappel 5 min avant deadline | Haut | Empeche les oublis, booste la conversion |
| Compteur de participants temps reel | Moyen | FOMO, preuve sociale |
| Suggestion de restos (base historique) | Haut | Resout le cold start |
| Rating post-commande (pouce haut/bas) | Moyen | Donne de la valeur aux donnees vendor |

### A PLANIFIER (Impact eleve, Effort important)

| Feature | Impact | Pourquoi |
|---------|--------|----------|
| Rotation runner automatique | Haut | Resout le probleme social du runner |
| Balance de remboursement entre users | Tres haut | Le graal du remboursement |
| Dashboard web admin avec analytics | Haut | Visibilite pour decision-makers |
| Sondage "Envie de quoi ?" pre-dejeuner | Haut | Engagement matinal, decouverte |

### A EVITER (pour l'instant)

| Feature | Pourquoi non |
|---------|-------------|
| Integration Uber Eats / Deliveroo | Trop complexe, API instables, detourne du core |
| Application mobile native | Le bot Slack EST l'interface, ne pas la dupliquer |
| Systeme de paiement integre | Responsabilite legale, compliance, trop tot |
| IA pour recommandations | Over-engineering, pas assez de data au debut |
| Multi-provider (Teams/Discord) | Focus Slack d'abord, valider le PMF |

---

## 6. PROFILS UTILISATEURS ET ADOPTION

### 6.1 Personas

| Persona | Motivation | Friction | Strategie |
|---------|-----------|----------|-----------|
| **L'Organisateur** | Simplifier sa vie | Setup initial, convaincre les autres | Resultats rapides, moins de charge mentale |
| **Le Genereux** | "Je vais chez X, qui veut ?" | Doit repondre a 8 messages | Le Quick Run formalise son habitude |
| **Le Suiveur** | Commander facilement | Doit trouver le bon message | 1 bouton, 1 clic, fini |
| **Le Sceptique** | "Ca marchait bien avant" | Changement d'habitude | Prouver la valeur par l'usage des autres |
| **Le Runner fatigue** | Rendre service | Toujours lui, pas de reconnaissance | Rotation visible, gratitude |
| **Le Manager** | Cohesion d'equipe | Pas de visibilite | Stats d'equipe, ROI en temps gagne |

### 6.2 Plan d'adoption en 4 semaines

**Semaine 1 : Prouver la valeur**
- Identifier 2-3 champions (les organisateurs naturels)
- Les former en 5 min (le bot doit etre SIMPLE)
- Objectif : 3-5 commandes/jour
- KPI : Au moins 1 session complete reussie (proposition → commande → cloture)

**Semaine 2 : Elargir le cercle**
- Les champions evangelisent naturellement via l'usage (messages visibles dans le channel)
- Le bot envoie des messages engageants qui donnent envie
- Objectif : 50% de l'equipe a commande au moins 1 fois
- KPI : 7+ commandes/jour

**Semaine 3 : Creer l'habitude**
- Le bot est devenu le canal par defaut pour le dejeuner
- Les conversations "on mange ou ?" se font via le bot
- Objectif : 70% des jours ouvrables ont une session active
- KPI : Retention J7 > 60%

**Semaine 4 : Ancrer le rituel**
- "On faisait comment avant le bot ?" → Point de non-retour
- Les nouveaux arrivants sont onboardes naturellement
- Objectif : Le bot est un reflexe, plus un outil
- KPI : Retention hebdomadaire > 80%

---

## 7. LE RITUEL : TRANSFORMER L'OUTIL EN EXPERIENCE

### 7.1 Vision

Le dejeuner n'est pas qu'un repas. C'est le moment social de la journee. Le bot doit capturer cette dimension.

**Actuellement** : "Commande → Mange → Oublie"
**Cible** : "Anticipe → Participe → Partage → Se souvient"

### 7.2 Les 4 moments d'engagement

**Le Morning Kick (anticipation)**

Chaque matin, 30 min avant l'heure de commande :
> "Bonjour l'equipe ! Hier vous avez adore Sushi Wasabi (8 commandes).
> Aujourd'hui, 3 options :
> - Sushi Wasabi (le classique)
> - Pizza Mario (pas commande depuis 2 semaines)
> - NOUVEAU : Thai Garden (ajoute par Marie)
>
> Ou proposez votre propre resto !"

**Le Rush Hour (excitation)**

Pendant la phase de commande, le message se met a jour :
> "Thai Garden - 6 commandes
> Runner : Thomas
> Sarah vient de commander ! Plus que 8 min..."

**Le Closing Recap (satisfaction)**

Apres cloture :
> "Dejeuner du jour - DONE !
> 8 commandes chez Thai Garden
> Total : 87.50 EUR (moy. 10.94 EUR/pers)
> Runner du jour : Thomas (son 5eme run ce mois !)
> Bon appetit !"

**Le Friday Digest (appartenance)**

Chaque vendredi :
> "RECAP DE LA SEMAINE
> 5/5 jours de commandes
> 42 commandes | 12 participants | 6 restaurants
>
> Top 3 restos :
> 1. Sushi Wasabi (14 commandes)
> 2. Pizza Mario (12)
> 3. Thai Garden (8) - NOUVEAU
>
> MVP Runner : Thomas (3 runs)
> Explorateur : Marie (4 restos differents)
> Fidele : Alex (5/5 jours)"

---

## 8. GAMIFICATION LEGERE

> Principe : Fun et legere, jamais culpabilisante. Desactivable par workspace.

### 8.1 Reconnaissance (pas competition)

| Element | Declencheur | Exemple |
|---------|------------|---------|
| Mention dans le recap | Etre runner | "MVP Runner : Thomas" |
| Streak affiche | X jours consecutifs | "5 jours d'affilee !" |
| Milestone collectif | Paliers d'equipe | "100eme commande !" |
| Bienvenue | Premiere utilisation | "Bienvenue @Sarah !" |

### 8.2 Badges (optionnels)

| Badge | Condition | Esprit |
|-------|-----------|--------|
| Premiere commande | 1 commande | Decouverte |
| Habitue | 20 commandes | Fidelite |
| Explorateur | 5 restos differents | Curiosite |
| Runner debutant | 1 run | Generosite |
| Runner confirme | 10 runs | Fiabilite |
| Streak 5 jours | 5 jours consecutifs | Regularite |
| Early bird | 3x premier a commander | Proactivite |

### 8.3 Anti-patterns

- Ne PAS afficher les montants depenses dans les classements
- Ne PAS creer de "dernier du classement"
- Ne PAS forcer la gamification (toujours optionnelle)
- Ne PAS envoyer trop de notifications (respecter l'attention)

---

## 9. COMPETITIVE MOAT

### 9.1 Analyse concurrentielle

| Solution alternative | Force | Faiblesse | Menace |
|---------------------|-------|-----------|--------|
| Messages Slack informels | Zero setup | Chaos, oublis, pas de tracking | Faible - c'est le probleme qu'on resout |
| Google Sheet partage | Flexible | Manuel, pas de notifs | Moyenne |
| Just Eat / Uber Eats groupe | Menu integre, paiement | Pas de coordination equipe, commissions | Faible - use case different |
| Solutions corporate (Frichti, Foodles) | Tout-en-un | Cher, choix limite, rigide | Moyenne pour grandes entreprises |

### 9.2 Avantages competitifs

**L'effet de reseau interne** : Plus l'equipe utilise le bot, plus il est utile. A 80% d'adoption, les 20% restants sont "forces" de suivre.

**L'historique** : Apres 3 mois, le bot connait les preferences, les habitudes, les restaurants preferes. Migrer = perdre tout ca.

**L'integration Slack native** : Zero installation, zero nouveau compte. Le bot vit la ou l'equipe travaille deja.

**La personnalisation** : Chaque workspace a ses propres restaurants, habitudes, regles. Un concurrent generique ne rivalise pas sur ce point.

### 9.3 Fosse defensif dans le temps

```
Mois 1   : INTEGRATION → Slack natif, zero friction
Mois 2-3 : DATA        → Historique, preferences, habitudes
Mois 4-6 : RESEAU      → Adoption equipe, norme sociale
Mois 6+  : HABITUDE    → Rituel quotidien, irreversible
```

---

## 10. INTELLIGENCE PRODUIT

### 10.1 Donnees generees (non exploitees)

| Donnee | Usage potentiel |
|--------|----------------|
| Frequence de commande par user | Detecter le desengagement, relancer |
| Restaurants les plus commandes | Suggestions intelligentes |
| Runs par personne | Rotation equitable, reconnaissance |
| Prix moyen par restaurant | Budget, comparaison |
| Jour/heure vs participation | Optimiser les horaires |

### 10.2 Insights actionnables

**Pour l'utilisateur** :
- "Ton restaurant prefere : Sushi Wasabi (12 commandes ce mois)"
- "Commande en 1 clic : ton habituel chez Thai Garden ?"

**Pour l'equipe** :
- "Participation en hausse de 20% cette semaine"
- "Nouveau record : 15 commandes en une journee !"

**Pour le manager** :
- "Taux d'adoption : 78% de l'equipe utilise le bot"
- "Estimation : 20 min/jour economisees vs coordination manuelle"

---

## 11. MONETISATION (vision future)

> La monetisation n'est PAS la priorite. L'objectif #1 est le Product-Market Fit.

**Gratuit (pour toujours)** :
- Jusqu'a 15 utilisateurs
- Session dejeuner + Quick Run
- Historique 30 jours

**Pro (9 EUR/mois par equipe)** :
- Utilisateurs illimites
- Analytics et recaps avances
- Historique illimite
- Multi-canal
- Rotation runner automatique
- Gamification

**Enterprise (sur devis)** :
- Multi-workspace
- Dashboard manager
- Export comptable
- Support prioritaire

---

## 12. METRIQUES DE SUCCES

| Metrique | Definition | Cible beta | Cible 3 mois |
|----------|-----------|------------|--------------|
| **DAU / Equipe** | Utilisateurs actifs quotidiens | 5+ | 10+ |
| **Commandes / jour** | Sessions + Quick Runs confondus | 5+ | 15+ |
| **Taux de completion** | Sessions avec au moins 1 commande / sessions totales | 70% | 90% |
| **Retention J7** | % users semaine 1 qui reviennent semaine 2 | 50% | 70% |
| **Time to first order** | Temps entre decouverte et premiere commande | < 2 min | < 1 min |
| **NPS** | Recommanderiez-vous Lunch Bot a une autre equipe ? | > 30 | > 50 |

---

## 13. ROADMAP BUSINESS

### Phase 0 : MVP Reset & Analyse de la Roadmap

> **Objectif** : Remettre a plat ce que le MVP doit contenir pour etre presentable. Definir le perimetre exact, couper ce qui n'est pas essentiel, identifier ce qui manque.

| Action | Description |
|--------|------------|
| Audit du flow Session Dejeuner | Qu'est-ce qui marche, qu'est-ce qui frictionne, qu'est-ce qu'on simplifie ? |
| Design du Quick Run | Definir le parcours complet : declenchement → collecte des demandes → cloture → recap |
| Definition du perimetre MVP | Liste exhaustive de ce qui est IN et ce qui est OUT pour la beta |
| Identification des beta-testeurs | 2-3 champions + 10-15 utilisateurs dans 1-2 equipes |
| Definition des KPIs beta | Quels chiffres valident le Product-Market Fit ? |
| Pitch interne | Le message d'annonce du bot pour les beta-testeurs (1 paragraphe, pas un manuel) |

### Phase 1 : Ship & Learn

> **Objectif** : Mettre le bot en production, observer le comportement reel.

| Action | Description |
|--------|------------|
| Lancer la beta avec le groupe pilote | Session dejeuner + Quick Run operationnels |
| Observer sans intervenir | Quels features sont utilisees ? A quelle frequence ? Par qui ? |
| Collecter du feedback qualitatif | Conversations informelles avec les beta-testeurs |
| Mesurer les KPIs de base | DAU, commandes/jour, retention J7 |
| Iterer sur les frictions observees | Ajuster le flow en fonction du terrain |

### Phase 2 : Retention & Engagement

> **Objectif** : Creer les boucles qui font revenir les utilisateurs chaque jour.

| Action | Description |
|--------|------------|
| Rappels pre-deadline | "Plus que X min, Y collegues ont commande" |
| Favoris / reorder rapide | "Ma commande habituelle" en 1 clic |
| Closing Recap enrichi | Stats, reconnaissance du runner |
| Friday Digest | Recap hebdomadaire dans le channel |
| Historique personnel | "Mes commandes" accessibles a tout moment |

### Phase 3 : Social & Discovery

> **Objectif** : Transformer l'outil en rituel social, pousser la decouverte culinaire.

| Action | Description |
|--------|------------|
| Morning Kick intelligent | Suggestions basees sur l'historique, rotation des restos |
| Tracking runner / suggestion de rotation | Visibilite sur qui fait des runs |
| Badges de base | Premiere commande, explorateur, runner |
| Restaurant de la semaine | Mise en avant de nouveaux restos |
| Rating post-commande | Pouce haut/bas sur les restos |

### Phase 4 : Scale

> **Objectif** : Etendre a d'autres equipes, preparer la monetisation.

| Action | Description |
|--------|------------|
| Balance de remboursement | Suivi des dettes entre collegues |
| Dashboard admin / stats equipe | Visibilite pour les managers |
| Multi-canal / multi-equipe | Scaling dans les grandes entreprises |
| Onboarding automatise | Installation simplifiee pour nouveaux workspaces |
| Landing page + pricing | Si validation PMF confirmee |

---

## 14. RESUME EXECUTIF

### Forces
- Probleme reel et quotidien, ressenti par toutes les equipes
- Integration Slack native (zero friction d'installation)
- Architecture multi-tenant (scalable a plusieurs entreprises)
- Potentiel viral naturel (chaque commande est visible dans le channel)

### Faiblesses critiques
- Produit purement utilitaire, aucune dimension sociale ou emotionnelle
- Zero mecanisme de retention
- Le Quick Run (use case "je vais chez X, qui veut quelque chose ?") n'est pas couvert
- Le probleme du runner est identifie mais non adresse
- Aucune exploitation des donnees generees

### Opportunites
- Marche non structure (la plupart des equipes utilisent des messages informels)
- Frequence d'usage quotidienne (rare pour un outil B2B)
- Donnees food uniques par entreprise (moat naturel)
- Le Quick Run capture un comportement existant sans changer les habitudes

### La priorite absolue

Creer une boucle d'engagement qui fait revenir les utilisateurs chaque jour sans effort. Le dejeuner est le moment social de la journee en entreprise. Le bot qui s'ancre dans ce moment gagne.

### Le test de reussite

Le produit a reussi quand quelqu'un dans l'equipe dit naturellement :

> *"Attends, passe par le bot pour qu'on sache qui veut quoi."*

A ce moment-la, le bot est devenu le standard.
