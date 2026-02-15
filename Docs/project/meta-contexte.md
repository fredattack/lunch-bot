# Meta Contexte Projet — Lunch Coordination Bot

> Document de référence stable — à modifier uniquement sur décision explicite.

---

## 1. 📋 Présentation du projet

Le projet consiste à développer un **outil de coordination des commandes de repas en équipe**, centré sur la réduction de la friction quotidienne liée au *"qui commande quoi, où, quand, et qui paye"*.

Le produit est conçu comme un **bot intégré aux outils de communication d'entreprise**, avec :

- **MVP :** Exclusivement sur Slack
- **Architecture :** Cœur métier indépendant du provider
- **Extensibilité :** Vers d'autres plateformes (Teams, etc.) et éventuellement application mobile

---

## 2. ✅ Décisions centrales

### Positionnement produit

- Le produit est un **outil de coordination**, pas un service de commande ou de paiement
- Le MVP fonctionne **uniquement via Slack**, sans application web ou mobile
- Le cœur métier est **découplé de Slack** (Slack = adaptateur d'interface)

### Architecture multi-tenant

- Le produit est **multi-tenant dès le MVP**
- Un tenant = une entreprise / workspace
- Un tenant est identifié par le workspace du provider (Slack au MVP)
- L'isolation des données est assurée par un **Global Scope**

### Modèle fonctionnel

- Une **session de repas unique par jour et par tenant**
- Une session peut être **multi-fournisseurs** (plusieurs restaurants le même jour)
- Chaque fournisseur dans une session a **un seul responsable opérationnel**
- Les prix peuvent être **estimés puis ajustés a posteriori**

### Technique

- Base de données MVP : **SQLite** (migration future anticipée)
- Droits "humains" : **Laravel Policies** ciblées sur `Vendor`, `VendorProposal`, `Order`
- **Tests automatisés requis** pour les policies
- Vocabulaire du code : **anglais** / Interface utilisateur : **français**

---

## 3. 🔒 Contraintes & Invariants

### Isolation & Sécurité

- Le produit ne doit **jamais mélanger les données entre tenants**
- Toute requête métier est **implicitement scopée par tenant**

### Indépendance

- Le domaine métier ne doit **jamais dépendre d'un provider spécifique**
- Le système ne doit **pas encaisser ni transiter de l'argent**

### Simplicité

- Le produit doit rester **simple, utilisable sans formation**
- Les responsabilités humaines (créateur, responsable, admin) sont **distinctes de l'isolation technique**

### Règles métier

- Une session de repas a un **cycle de vie borné** (ouverte → clôturée)
- Une proposition de fournisseur a **exactement un responsable** à un instant donné
- Le **prix final est la référence de paiement**, même s'il diffère du prix estimé

---

## 4. 🚫 Hors périmètre (Non-Goals)

| Catégorie | Exclusion |
|-----------|-----------|
| **Fonctionnel** | Pas de gestion de menus structurés |
| **Fonctionnel** | Pas de scraping de sites de restaurants |
| **Paiement** | Pas de paiement intégré (Stripe, PSP, etc.) |
| **Paiement** | Pas de gestion comptable ou légale des paiements |
| **Interface** | Pas d'application mobile au MVP |
| **Interface** | Pas de frontend web |
| **Technique** | Pas de système de rôles ou permissions complexe |
| **Technique** | Pas de scaling horizontal ou haute disponibilité au MVP |

---

## 5. 📖 Vocabulaire partagé

| Terme | Définition |
|-------|------------|
| **Tenant** | Organisation cliente, correspondant à un workspace du provider |
| **LunchSession** | Session de coordination d'un repas collectif (généralement une journée) |
| **Vendor** | Fournisseur de nourriture (restaurant, dark kitchen, plateforme) |
| **VendorProposal** | Proposition active d'un Vendor dans une LunchSession |
| **Order** | Commande individuelle passée par un utilisateur pour un Vendor donné |
| **Responsable retrait** | Personne chargée d'aller chercher la commande sur place |
| **Responsable commande** | Personne chargée de passer la commande en ligne et gérer la livraison |
| **Admin tenant** | Utilisateur disposant de droits étendus au sein d'un tenant |
| **Global Scope** | Mécanisme Laravel garantissant l'isolation des données par tenant |

---

## 6. ❓ Questions ouvertes

- À quel moment migrer officiellement de **SQLite vers MySQL ou PostgreSQL** ?
- Jusqu'où aller dans l'intégration future des **outils de paiement tiers** (ex. Tricount) ?
- Faut-il généraliser `LunchSession` vers un **concept plus large** (ex. autres types de repas) à long terme ?

---

> **Ce document constitue le Meta Contexte de référence du projet et doit être considéré comme stable tant qu'une décision explicite ne vient pas le modifier.**
