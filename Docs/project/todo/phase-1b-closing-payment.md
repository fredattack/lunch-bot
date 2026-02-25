# Phase 1b — Cloture & Facilitation de Paiement

**Date** : 22 fevrier 2026
**Phase** : 1b (sous-phase de Phase 1 - Retention & Engagement)
**Objectif** : Transformer la phase de cloture en experience fluide de paiement. Resoudre le "last mile" de la coordination dejeuner : la collecte d'argent.

---

## 1. Vision & Proposition de valeur

### Le probleme du "last mile"

Le MVP de Lunch Bot resout brillamment la coordination (qui commande quoi, ou, quand). Mais il s'arrete au moment le plus critique : le remboursement. Aujourd'hui, apres cloture, le bot affiche une liste brute de montants. Le runner doit ensuite :

1. Retenir ou noter qui lui doit quoi
2. Communiquer son IBAN a chacun par message prive
3. Verifier manuellement chaque virement recu
4. Relancer les retardataires un par un, sans paraitre insistant
5. Faire le suivi mental de l'etat des paiements pendant des jours

C'est exactement la **Friction #6** identifiee dans l'audit business : "Aucun mecanisme de paiement, juste un affichage." C'est aussi la **Faille #5** : "Le remboursement reste un cauchemar."

### Pourquoi c'est le killer feature

La coordination dejeuner a un cycle complet : **proposer > commander > aller chercher > payer**. Le MVP couvre les trois premiers. Sans le quatrieme, le cycle reste ouvert. Chaque dette non reglee est une dette sociale qui s'accumule, genere de la frustration, et finit par decourager les runners.

Un runner qui ne se fait pas rembourser facilement arrete de faire des runs. Quand les runners decrochent, tout le groupe suit (cf. Faille #4 de l'audit business). La facilitation de paiement est donc directement liee a la retention.

### Proposition de valeur Phase 1b

*Apres cloture, le runner clique un bouton. Chaque commandeur recoit un DM avec le montant exact, un QR code SEPA scannable, et un bouton pour confirmer le paiement. Le runner suit tout en temps reel. Zero charge mentale.*

### Impact attendu

| Metrique | Avant Phase 1b | Apres Phase 1b (cible) |
|----------|----------------|------------------------|
| Taux de remboursement complet (meme journee) | Non mesure (~50% estime) | 80%+ |
| Temps moyen pour etre rembourse | Plusieurs jours | Moins de 2 heures |
| Satisfaction runner | Frustration post-cloture | Experience complete et fluide |
| Volontariat runner | En baisse | Stable ou en hausse |

---

## 2. Contraintes architecturales

### 2.1 Contrainte meta-contexte : pas de transit d'argent

Le meta-contexte est explicite :

> "Le systeme ne doit pas encaisser ni transiter de l'argent."

La Phase 1b respecte strictement cette contrainte. Le bot **facilite** le paiement mais ne le **traite** pas :

- Il genere un QR code SEPA que l'utilisateur scanne dans **son propre app bancaire**
- Il affiche un IBAN pour un virement **direct entre particuliers**
- Le bouton "J'ai paye" est **declaratif** : le bot enregistre l'intention, pas la transaction
- Aucune verification bancaire, aucun encaissement, aucun transit de fonds

Le bot est un **facilitateur d'information**, pas un intermediaire financier.

### 2.2 Systeme declaratif et confiance

Le systeme repose sur la **confiance entre collegues**. Quand un commandeur clique "J'ai paye", le bot le croit. Pas de verification bancaire, pas de preuve de virement. Ce choix est delibere :

- Le contexte d'usage est un **petit groupe de collegues** qui se voient chaque jour
- La pression sociale est un mecanisme de controle suffisant
- Ajouter de la verification bancaire transformerait le bot en service financier (hors scope)
- La simplicite du systeme est un avantage : zero setup bancaire cote bot

### 2.3 RGPD et donnees sensibles

L'IBAN est une donnee personnelle sensible au sens du RGPD. Les contraintes sont detaillees en section 8.

### 2.4 Coherence avec l'architecture existante

- **Action Pattern** : chaque operation metier est encapsulee dans une Action
- **Provider Pattern** : les `provider_user_id` sont utilises, pas de dependance directe a Slack
- **Multi-tenant** : toutes les nouvelles tables incluent `organization_id` et le `BelongsToOrganization` scope
- **Slack comme adaptateur** : la logique de paiement est dans le domaine metier, Slack n'est que le canal de presentation

---

## 3. Modele de donnees

### 3.1 PaymentRequest

Represente une demande de paiement individuelle : un commandeur doit un montant a un runner pour une proposition donnee.

```
Table: payment_requests
```

| Champ | Type | Description |
|-------|------|-------------|
| `id` | bigint PK | Identifiant unique |
| `organization_id` | bigint FK | Tenant (scope obligatoire) |
| `vendor_proposal_id` | bigint FK | Proposition concernee |
| `debtor_provider_user_id` | string | ID provider du commandeur (celui qui doit payer) |
| `creditor_provider_user_id` | string | ID provider du runner (celui qui doit recevoir) |
| `amount` | decimal(10,2) | Montant du (base sur price_final ou price_estimated) |
| `currency` | string(3) | Devise (defaut: EUR) |
| `reference` | string | Communication structuree (ex: LUNCH-2026-02-22-42) |
| `status` | enum | pending, paid, disputed |
| `dm_message_ts` | string nullable | Timestamp du DM Slack envoye au debiteur |
| `dm_channel_id` | string nullable | ID du canal DM Slack |
| `paid_at` | datetime nullable | Date/heure de la declaration de paiement |
| `reminded_at` | datetime nullable | Date/heure du dernier rappel envoye |
| `reminder_count` | int default 0 | Nombre de relances envoyees |
| `created_at` | datetime | Date de creation |
| `updated_at` | datetime | Derniere mise a jour |

**Relations :**
- `belongsTo VendorProposal`
- `belongsTo Organization` (via BelongsToOrganization)

**Statuts :**
- `Pending` : DM envoye, en attente de paiement
- `Paid` : Le commandeur a declare avoir paye
- `Disputed` : Le commandeur a signale un probleme

### 3.2 UserPaymentProfile

Stocke les informations bancaires d'un utilisateur pour faciliter les paiements.

```
Table: user_payment_profiles
```

| Champ | Type | Description |
|-------|------|-------------|
| `id` | bigint PK | Identifiant unique |
| `organization_id` | bigint FK | Tenant |
| `provider_user_id` | string | ID provider de l'utilisateur |
| `iban` | text (encrypted) | IBAN chiffre (cast `encrypted`) |
| `account_holder_name` | text (encrypted) | Nom du titulaire chiffre |
| `bic` | string nullable | Code BIC/SWIFT (optionnel, version EPC 002 le rend optionnel) |
| `created_at` | datetime | Date de creation |
| `updated_at` | datetime | Derniere mise a jour |

**Contrainte d'unicite :** `(organization_id, provider_user_id)` — un seul profil de paiement par utilisateur et par tenant.

**Chiffrement :** Les champs `iban` et `account_holder_name` utilisent le cast `encrypted` de Laravel (meme pattern que `bot_token` dans `OrganizationInstallation`).

### 3.3 Enum PaymentRequestStatus

```php
enum PaymentRequestStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Disputed = 'disputed';
}
```

### 3.4 Diagramme de relations

```
VendorProposal (existant)
    |
    +-- hasMany PaymentRequest
            |
            +-- debtor_provider_user_id  --> (utilisateur)
            +-- creditor_provider_user_id --> (runner)

UserPaymentProfile (standalone)
    +-- provider_user_id --> (utilisateur)
    +-- organization_id --> Organization
```

---

## 4. Stories detaillees

### Story 10.1 — DM individuel de paiement

**User Story :**
> En tant que runner, apres la cloture d'une proposition, je peux cliquer "Demander le paiement" pour que le bot envoie un DM a chaque commandeur avec le detail de sa commande et le montant du.

**Criteres d'acceptation :**

1. Apres la cloture d'une proposition, le runner voit un bouton "Demander le paiement" dans le dashboard (etat S5) ou dans le recap de cloture
2. Au clic, le bot cree un `PaymentRequest` par commandeur (hors le runner lui-meme s'il a commande)
3. Le montant est calcule sur `price_final` si disponible, sinon `price_estimated`
4. Si un commandeur a plusieurs commandes sur la meme proposition, les montants sont agreges en un seul `PaymentRequest`
5. Le bot envoie un DM a chaque commandeur via `chat.postMessage` avec `channel` = user ID (ouvre un DM)
6. Le DM contient : nom du restaurant, description de la commande, montant du, nom du runner
7. Le `dm_message_ts` et `dm_channel_id` sont stockes sur le `PaymentRequest` pour reference
8. Si le runner n'a pas configure son IBAN, un message l'avertit et lui propose de le configurer avant d'envoyer les DMs (les DMs sont envoyes quand meme, sans QR code ni IBAN)
9. Le bouton "Demander le paiement" est desactive apres le premier clic (pas de double envoi)
10. Les DMs sont envoyes de maniere asynchrone (job queued) pour eviter les timeouts Slack

**Notes d'implementation :**

- Nouvelle Action : `Payment/RequestPayments`
- Nouveau Handler dans `Handlers/PaymentInteractionHandler`
- Nouveau SlackAction : `PaymentRequestSend`
- Le DM utilise `chat.postMessage` avec le `user_id` comme `channel` (Slack ouvre automatiquement un DM)
- Scope OAuth necessaire : `chat:write` (deja present)
- Job queued : `SendPaymentDMs` pour traiter les envois en arriere-plan

**UX Flow (contenu du DM) :**

```
+----------------------------------------------+
|  Commande chez Pizza Mario                    |
|  22 fevrier 2026                              |
|                                               |
|  Ta commande :                                |
|  Margherita + Tiramisu                        |
|                                               |
|  Montant : 14.50 EUR                          |
|                                               |
|  A payer a Thomas                             |
|  [IBAN et QR code si disponibles - story 10.2/10.3]  |
|                                               |
|  [ J'ai paye ]     [ Probleme ]               |
+----------------------------------------------+
```

**Edge cases :**

- Le runner a aussi commande pour lui-meme : pas de DM a soi-meme, pas de PaymentRequest
- Aucune commande sur la proposition : le bouton n'apparait pas
- Tous les prix sont a 0.00 : pas de PaymentRequest cree
- Le commandeur a quitte le workspace Slack : le DM echoue silencieusement, le PaymentRequest est cree avec `dm_message_ts = null`
- La proposition a deja eu des PaymentRequests (double-clic malgre desactivation) : idempotent, pas de doublons
- Quick Run : le mecanisme pourrait s'appliquer a terme, mais est hors perimetre Phase 1b (cf. section 11). La story 10.1 couvre uniquement les sessions dejeuner.

---

### Story 10.2 — Numero de compte bancaire du runner

**User Story :**
> En tant que commandeur, dans le DM de paiement, je vois automatiquement l'IBAN du runner pour faciliter le virement.

**Criteres d'acceptation :**

1. Si le runner a configure son IBAN (story 10.7), le DM inclut une section avec :
   - Nom du titulaire du compte
   - IBAN formate lisiblement (groupes de 4 caracteres)
   - BIC si renseigne
2. Si le runner n'a pas configure son IBAN, le DM affiche un message neutre : "Contacte Thomas pour ses coordonnees bancaires."
3. L'IBAN est affiche en texte dans le DM (pas dans une image) pour permettre le copier-coller
4. L'IBAN est formate selon la norme : `BE68 5390 0754 7034` (espaces tous les 4 caracteres)

**Notes d'implementation :**

- Le `PaymentRequest` est cree par la story 10.1, cette story enrichit le contenu du DM
- Lecture du `UserPaymentProfile` du runner au moment de la construction du DM
- Le champ `iban` est dechiffre a la lecture grace au cast `encrypted`

**Edge cases :**

- IBAN invalide (format incorrect) : afficher tel quel, la validation se fait a la saisie (story 10.7)
- Runner sans profil de paiement : message alternatif sans IBAN ni QR code
- Plusieurs runners sur la meme session (propositions differentes) : chaque DM affiche l'IBAN du runner de sa proposition

---

### Story 10.3 — QR Code SEPA (EPC QR Code)

**User Story :**
> En tant que commandeur, dans le DM de paiement, je vois un QR code EPC que je peux scanner avec mon app bancaire pour payer en 10 secondes.

**Criteres d'acceptation :**

1. Si le runner a configure son IBAN, le DM inclut un QR code EPC (European Payments Council)
2. Le QR code est conforme au standard EPC version 002 (BIC optionnel)
3. Le QR code est pre-rempli avec :
   - Service Tag : BCD
   - Version : 002
   - Character set : 1 (UTF-8)
   - Identification : SCT (SEPA Credit Transfer)
   - BIC : vide ou valeur du profil runner (optionnel en v002)
   - Beneficiaire : nom du titulaire du compte du runner
   - IBAN : IBAN du runner
   - Montant : format EUR avec 2 decimales (ex: EUR14.50)
   - Purpose : vide
   - Remittance (communication) : reference structuree (ex: LUNCH-2026-02-22)
   - Information : vide
4. Le QR code est genere cote serveur en PNG (300x300 px minimum)
5. Le QR code est uploade dans le DM via l'API Slack (fichier joint au message)
6. Le QR code est scannable par les principales apps bancaires belges (KBC, BNP Paribas Fortis, Belfius, ING)
7. Le niveau de correction d'erreur est M (impose par le standard EPC)
8. La version QR ne depasse pas 13 (impose par le standard EPC)

**Specification technique du format EPC QR Code :**

Le contenu textuel du QR code suit un format ligne par ligne (separateur `\n`) :

```
BCD                          <- Service Tag (fixe)
002                          <- Version (002 = BIC optionnel)
1                            <- Character set (1 = UTF-8)
SCT                          <- Identification (SEPA Credit Transfer)
                             <- BIC (optionnel en v002)
Thomas Dupont                <- Nom du beneficiaire (max 70 car.)
BE68539007547034             <- IBAN (sans espaces)
EUR14.50                     <- Montant (format EURX.XX)
                             <- Purpose code (vide)
LUNCH-2026-02-22             <- Remittance / Communication (max 140 car.)
                             <- Information (vide)
```

**Notes d'implementation :**

- Librairie recommandee : `ccharz/laravel-epc-qr` (wrapper Laravel pour `endroid/qr-code` 5.x avec support EPC natif)
- Alternative : `endroid/qr-code` 5.x + construction manuelle du payload EPC
- Nouvelle classe service : `EpcQrCodeGenerator`
- Upload Slack : utiliser la sequence `files.getUploadURLExternal` + upload + `files.completeUploadExternal` (l'ancienne `files.upload` est deprecee depuis mars 2025)
- Le QR code est genere en memoire (pas de stockage sur disque), uploade, puis libere
- Nouveau scope OAuth potentiellement necessaire : `files:write`

**Flux de generation et upload :**

```
1. Lire UserPaymentProfile du runner
2. Construire le payload EPC (texte multiligne)
3. Generer le QR code PNG en memoire (EpcQrCodeGenerator)
4. Appeler files.getUploadURLExternal (taille du fichier)
5. Uploader le PNG vers l'URL retournee (PUT)
6. Appeler files.completeUploadExternal (file_id, channel_id du DM)
7. Le fichier apparait dans le DM du commandeur
```

**Edge cases :**

- Montant > 999999.99 EUR : techniquement valide en EPC, mais improbable (pas de garde)
- Nom du beneficiaire > 70 caracteres : tronquer a 70 caracteres
- Communication > 140 caracteres : tronquer a 140 caracteres
- Runner sans IBAN : pas de QR code genere, le reste du DM est envoye normalement
- Echec de l'upload Slack : le DM est envoye sans image, l'IBAN en texte reste disponible
- IBAN non-SEPA (hors zone SEPA) : le QR code EPC ne supporte que les pays SEPA, afficher un avertissement

---

### Story 10.4 — Bouton "J'ai paye"

**User Story :**
> En tant que commandeur, dans le DM de paiement, je peux cliquer "J'ai paye" pour declarer que j'ai effectue le virement.

**Criteres d'acceptation :**

1. Le DM contient deux boutons : "J'ai paye" (primary) et "Probleme" (danger)
2. Au clic sur "J'ai paye" :
   - Le `PaymentRequest.status` passe a `paid`
   - Le `PaymentRequest.paid_at` est renseigne
   - Le DM est mis a jour : les boutons disparaissent, remplace par un message de confirmation ("Paiement declare le 22/02 a 13:42")
   - Le runner recoit une notification (DM ou message ephemeral dans le channel)
3. Au clic sur "Probleme" :
   - Le `PaymentRequest.status` passe a `disputed`
   - Le DM est mis a jour avec un message : "Signalement enregistre. Contacte Thomas directement."
   - Le runner recoit une notification que le commandeur a signale un probleme
4. Le bouton "J'ai paye" est cliquable une seule fois (le message est mis a jour pour retirer les boutons)
5. Un commandeur ne peut pas marquer "J'ai paye" pour un autre commandeur

**Notes d'implementation :**

- Nouveaux SlackAction : `PaymentMarkPaid`, `PaymentReportIssue`
- Le `value` du bouton contient le `PaymentRequest.id`
- Mise a jour du DM via `chat.update` avec le `dm_channel_id` et `dm_message_ts`
- Notification au runner via `chat.postMessage` vers le user ID du runner (DM)
- Action : `Payment/MarkAsPaid`, `Payment/ReportIssue`

**UX du DM apres clic "J'ai paye" :**

```
+----------------------------------------------+
|  Commande chez Pizza Mario                    |
|  22 fevrier 2026                              |
|                                               |
|  Ta commande :                                |
|  Margherita + Tiramisu                        |
|                                               |
|  Montant : 14.50 EUR                          |
|  Paiement declare le 22/02 a 13:42            |
+----------------------------------------------+
```

**Edge cases :**

- Le commandeur clique "J'ai paye" sans avoir reellement paye : le systeme est declaratif, pas de verification
- Le DM a ete supprime par le commandeur : le clic ne fonctionnera plus (message_not_found), le PaymentRequest reste en `pending`
- Latence reseau : le bouton peut etre clique deux fois rapidement, l'action doit etre idempotente
- Le runner a quitte le workspace : la notification echoue silencieusement

---

### Story 10.5 — Tableau de suivi pour le runner

**User Story :**
> En tant que runner, je peux consulter un tableau de suivi des paiements qui me montre qui a paye, qui n'a pas encore paye, et le montant restant.

**Criteres d'acceptation :**

1. Le runner peut acceder au tableau de suivi via :
   - Un bouton dans le dashboard (`/lunch`) quand la proposition est cloturee
   - Un lien dans la notification recue quand un commandeur declare avoir paye
2. Le tableau affiche pour chaque commandeur :
   - Nom (mention Slack `<@user_id>`)
   - Montant du
   - Statut : "Paye" (avec date) / "En attente" / "Probleme"
3. Le tableau affiche un resume en bas :
   - Total recu (somme des `paid`)
   - Total restant (somme des `pending`)
   - Total global
4. Le tableau se met a jour quand le runner le consulte (pas de push en temps reel, mais les donnees sont fraiches a chaque ouverture)
5. Le tableau inclut un bouton "Relancer les en attente" (story 10.6)

**Notes d'implementation :**

- Le tableau est affiche dans un modal Slack (ouvert via `views.open`)
- Nouveau SlackAction : `PaymentOpenTracker`
- Nouveau callback : `CallbackPaymentTracker`
- Construction des blocks via `SlackBlockBuilder::paymentTrackerBlocks()`
- Le modal est en lecture seule (pas de submit), juste des boutons d'action

**UX du modal :**

```
+----------------------------------------------+
|  Paiements - Pizza Mario (22/02)              |
|                                               |
|  Sarah -- 12.50 EUR -- Paye (13:42)           |
|  Alex -- 8.00 EUR -- Paye (13:55)             |
|  Marie -- 14.50 EUR -- En attente             |
|  Julien -- 11.00 EUR -- En attente            |
|                                               |
|  ---                                          |
|  Recu : 20.50 EUR / 46.00 EUR                 |
|                                               |
|  [ Relancer les en attente ]                  |
+----------------------------------------------+
```

**Edge cases :**

- Aucun paiement en attente (tous ont paye) : le bouton "Relancer" n'apparait pas, message de felicitation
- Aucun PaymentRequest cree (le runner n'a pas encore demande le paiement) : message invitant a demander le paiement d'abord
- Le runner consulte le tracker d'une ancienne session : les donnees sont toujours disponibles
- Un commandeur a un statut "Probleme" : affiche en rouge avec mention explicite

---

### Story 10.6 — Relance des paiements

**User Story :**
> En tant que runner, je peux relancer les commandeurs qui n'ont pas encore paye en leur renvoyant un DM de rappel.

**Criteres d'acceptation :**

1. Dans le tableau de suivi (story 10.5), un bouton "Relancer les en attente" est disponible
2. Au clic, le bot envoie un DM de rappel a chaque commandeur dont le `PaymentRequest.status` est `pending`
3. Le DM de rappel contient :
   - Reference a la commande originale (restaurant, date)
   - Montant du
   - IBAN et QR code (si disponibles, memes que le DM initial)
   - Bouton "J'ai paye" et "Probleme" (memes que le DM initial)
4. Limite anti-spam : maximum 1 relance par jour et par PaymentRequest
   - Si une relance a ete envoyee dans les dernieres 24h, le bouton est desactive avec un message explicatif
5. Le `PaymentRequest.reminded_at` est mis a jour a chaque relance
6. Le `PaymentRequest.reminder_count` est incremente a chaque relance
7. Le runner recoit un message de confirmation : "Rappel envoye a X personnes"
8. Si aucun PaymentRequest n'est en `pending`, le bouton n'apparait pas

**Notes d'implementation :**

- Nouveau SlackAction : `PaymentSendReminder`
- Action : `Payment/SendReminders`
- Job queued : `SendPaymentReminders` (meme pattern que l'envoi initial)
- La verification du delai de 24h se fait sur `reminded_at` du PaymentRequest

**Contenu du DM de rappel :**

```
+----------------------------------------------+
|  Rappel de paiement                           |
|                                               |
|  Commande chez Pizza Mario (22/02)            |
|  Montant : 14.50 EUR                          |
|                                               |
|  [IBAN et QR code si disponibles]             |
|                                               |
|  [ J'ai paye ]     [ Probleme ]               |
+----------------------------------------------+
```

**Edge cases :**

- Relance sur un PaymentRequest dont le DM initial a echoue : le rappel tente de renvoyer le DM (nouvel essai)
- Le commandeur a paye entre le clic sur "Relancer" et l'envoi du DM : verifier le statut avant envoi
- Tous les commandeurs ont deja ete relances dans les 24h : message d'information au runner, pas de relance envoyee
- Le `reminder_count` atteint un seuil eleve (ex: 5+) : pas de blocage, mais un warning au runner pourrait etre pertinent en Phase 2

---

### Story 10.7 — Configuration IBAN / compte bancaire

**User Story :**
> En tant qu'utilisateur, je peux configurer mon IBAN dans mes preferences pour qu'il soit utilise automatiquement quand je suis runner.

**Criteres d'acceptation :**

1. L'utilisateur peut acceder a la configuration IBAN via :
   - La commande `/lunch` > menu preferences (nouveau bouton dans le dashboard)
   - Un bouton contextuel dans le message d'avertissement quand le runner n'a pas d'IBAN (story 10.1)
2. Un modal Slack s'ouvre avec les champs :
   - IBAN (obligatoire, validation de format)
   - Nom du titulaire (obligatoire, pre-rempli avec le nom Slack de l'utilisateur)
   - BIC/SWIFT (optionnel)
3. A la soumission :
   - L'IBAN est valide (format, longueur, checksum)
   - Les donnees sont chiffrees et stockees dans `UserPaymentProfile`
   - Un message de confirmation est affiche
4. Si un profil existe deja, les champs sont pre-remplis (l'IBAN est dechiffre pour l'affichage dans le modal)
5. L'utilisateur peut supprimer son profil de paiement (bouton "Supprimer mes coordonnees bancaires")
6. Validation IBAN :
   - Format : 2 lettres (pays) + 2 chiffres (controle) + jusqu'a 30 caracteres alphanumeriques
   - Checksum : validation modulo 97 conforme a la norme ISO 13616
   - Les espaces sont automatiquement retires avant stockage

**Notes d'implementation :**

- Nouveau SlackAction : `PaymentOpenIbanSetup`, `CallbackPaymentIbanSave`
- Action : `Payment/SavePaymentProfile`, `Payment/DeletePaymentProfile`
- La validation IBAN peut utiliser un package comme `ixudra/iban` ou une validation manuelle (le checksum mod97 est simple a implementer)
- Pre-remplissage du nom : appel `users.info` pour recuperer le `real_name` de l'utilisateur Slack
- Le modal inclut un disclaimer RGPD (texte sous les champs)

**UX du modal :**

```
+----------------------------------------------+
|  Coordonnees bancaires                        |
|                                               |
|  IBAN *                                       |
|  [ BE68 5390 0754 7034               ]        |
|                                               |
|  Nom du titulaire *                           |
|  [ Thomas Dupont                      ]       |
|                                               |
|  BIC / SWIFT (optionnel)                      |
|  [ GKCCBEBB                           ]       |
|                                               |
|  Tes coordonnees sont chiffrees et            |
|  utilisees uniquement pour faciliter          |
|  les remboursements entre collegues.          |
|  Tu peux les supprimer a tout moment.         |
|                                               |
|  [ Supprimer mes coordonnees ]                |
|                                               |
|        [ Annuler ]    [ Enregistrer ]          |
+----------------------------------------------+
```

**Edge cases :**

- IBAN invalide (checksum) : message d'erreur clair dans le modal ("Format IBAN invalide")
- IBAN hors zone SEPA (ex: US, UK post-Brexit) : avertissement que le QR code EPC ne fonctionnera pas, mais autoriser l'enregistrement
- L'utilisateur saisit un IBAN avec des espaces/tirets : nettoyer automatiquement
- Suppression du profil : confirmation requise, les PaymentRequests existants ne sont pas affectes (l'IBAN etait deja dans le QR code au moment de l'envoi)
- Changement d'IBAN apres envoi de DMs : les DMs deja envoyes contiennent l'ancien IBAN, pas de mise a jour retroactive (choix delibere pour eviter la complexite)

---

## 5. Specification technique du QR Code EPC

### 5.1 Standard EPC (European Payments Council)

Le QR code EPC est defini par le document "Quick Response Code: Guidelines to Enable Data Capture for the Initiation of a SEPA Credit Transfer" publie par l'European Payments Council.

**Contraintes techniques du standard :**

| Parametre | Valeur |
|-----------|--------|
| Type de code | QR Code |
| Niveau de correction d'erreur | M (obligatoire) |
| Version maximale | 13 |
| Taille maximale du payload | 331 octets |
| Encodage | UTF-8 (character set = 1) |

### 5.2 Format du payload

Le payload est un texte multiligne (separateur `\n`, 12 lignes) :

```
Ligne 1  : Service Tag         -> "BCD" (fixe)
Ligne 2  : Version             -> "002" (BIC optionnel)
Ligne 3  : Character set       -> "1" (UTF-8)
Ligne 4  : Identification      -> "SCT" (SEPA Credit Transfer)
Ligne 5  : BIC                 -> BIC/SWIFT ou vide
Ligne 6  : Beneficiaire        -> Nom (max 70 caracteres)
Ligne 7  : IBAN                -> Sans espaces (ex: BE68539007547034)
Ligne 8  : Montant             -> "EUR" + montant (ex: EUR14.50)
Ligne 9  : Purpose code        -> vide (optionnel)
Ligne 10 : Remittance          -> Communication (max 140 caracteres)
Ligne 11 : Information         -> vide (optionnel)
```

### 5.3 Librairies PHP recommandees

**Option A (recommandee) : `ccharz/laravel-epc-qr`**

Package Laravel dedie qui encapsule `endroid/qr-code` 5.x avec une API fluide pour les QR codes EPC.

```
composer require ccharz/laravel-epc-qr
```

Avantages :
- API chainable specifique EPC
- Validation automatique du format EPC
- Integration Laravel native
- Maintenance active

**Option B : `endroid/qr-code` + construction manuelle**

```
composer require endroid/qr-code
```

Construction manuelle du payload EPC + generation du QR code via le Builder `endroid`.

### 5.4 Flux de generation et upload Slack

```
EpcQrCodeGenerator::generate(PaymentRequest $request, UserPaymentProfile $profile)
    |
    +-- 1. Construire le payload EPC (12 lignes)
    +-- 2. Generer le QR code PNG en memoire (300x300px, correction M)
    +-- 3. Retourner le contenu binaire PNG
    |
SlackService::uploadFile(string $channelId, string $content, string $filename)
    |
    +-- 4. POST files.getUploadURLExternal (length, filename)
    +-- 5. PUT upload_url (contenu binaire)
    +-- 6. POST files.completeUploadExternal (file_id, channel_id)
    +-- 7. Retourner le file_id pour reference
```

### 5.5 Compatibilite apps bancaires

Le standard EPC est supporte par les principales apps bancaires en Belgique :

| App bancaire | Support EPC QR |
|-------------|----------------|
| KBC/CBC Mobile | Oui |
| Belfius Mobile | Oui |
| BNP Paribas Fortis Easy Banking | Oui |
| ING Banking | Oui |
| Argenta | Oui |
| Payconiq by Bancontact | Non (standard different) |

---

## 6. Securite & RGPD

### 6.1 Donnees concernees

| Donnee | Classification | Traitement |
|--------|---------------|------------|
| IBAN | Donnee personnelle sensible | Chiffrement au repos (Laravel `encrypted` cast) |
| Nom du titulaire | Donnee personnelle | Chiffrement au repos |
| BIC | Donnee semi-publique | Stockage en clair (info publique par banque) |
| Montants des commandes | Donnee personnelle | Deja present dans le modele existant |

### 6.2 Chiffrement

- **Methode** : Laravel `encrypted` cast (utilise `APP_KEY` via OpenSSL AES-256-CBC)
- **Pattern existant** : identique a `OrganizationInstallation.bot_token` et `OrganizationInstallation.signing_secret`
- **Portee** : chiffrement au repos dans la base de donnees. En memoire, les donnees sont en clair le temps du traitement.

### 6.3 Retention des donnees

| Donnee | Duree de retention | Justification |
|--------|-------------------|---------------|
| `UserPaymentProfile` | Jusqu'a suppression par l'utilisateur | L'utilisateur controle ses donnees |
| `PaymentRequest` | 12 mois apres creation | Historique de suivi, puis purge automatique |
| QR code PNG | Non stocke | Genere en memoire, uploade, puis libere |

### 6.4 Droits des utilisateurs (RGPD)

- **Acces** : l'utilisateur voit son IBAN dans le modal de configuration
- **Rectification** : l'utilisateur peut modifier son IBAN a tout moment
- **Suppression** : l'utilisateur peut supprimer son profil de paiement via le modal (story 10.7)
- **Portabilite** : non applicable (l'utilisateur connait deja son propre IBAN)
- **Opposition** : l'utilisateur peut choisir de ne pas configurer son IBAN

### 6.5 Consentement

Le consentement est implicite par l'action de l'utilisateur :
- Configurer son IBAN = consentir a son utilisation dans les DMs de paiement
- Le disclaimer dans le modal informe clairement de l'usage

### 6.6 Acces aux donnees

- Seul le proprietaire du profil peut voir/modifier/supprimer son IBAN
- Les commandeurs voient l'IBAN du runner uniquement dans le contexte d'un DM de paiement (temporaire, dans le message Slack)
- Les admins du tenant n'ont pas acces aux IBANs des utilisateurs (pas de dashboard admin pour les donnees bancaires)

---

## 7. Priorite et sequencage

### 7.1 Ordre de construction

Les stories ont des dependances claires qui dictent l'ordre de realisation.

```
Phase A : Fondations
    10.7 - Configuration IBAN (prerequis pour 10.2 et 10.3)
    10.1 - DM individuel de paiement (coeur du systeme)

Phase B : Enrichissement du DM
    10.2 - IBAN dans le DM (depend de 10.7)
    10.3 - QR Code SEPA (depend de 10.7 et 10.2)
    10.4 - Bouton "J'ai paye" (depend de 10.1)

Phase C : Suivi et relance
    10.5 - Tableau de suivi runner (depend de 10.1 et 10.4)
    10.6 - Relance des paiements (depend de 10.5)
```

### 7.2 Graphe de dependances

```
10.7 (IBAN config)
  |
  +----> 10.2 (IBAN dans DM)
  |         |
  |         +----> 10.3 (QR Code EPC)
  |
10.1 (DM de paiement) -----> 10.4 (Bouton J'ai paye)
                                      |
                                      +----> 10.5 (Tableau de suivi)
                                                    |
                                                    +----> 10.6 (Relance)
```

### 7.3 Livraison incrementale

La Phase 1b peut etre livree en 3 increments fonctionnels :

**Increment 1 (utilisable immediatement) :**
- 10.7 + 10.1 + 10.2 + 10.4
- Le runner peut demander le paiement, les commandeurs recoivent un DM avec IBAN et peuvent confirmer
- Valeur livree : le flux de base est complet

**Increment 2 (amelioration UX) :**
- 10.3
- Ajout du QR code SEPA dans les DMs
- Valeur livree : paiement en 10 secondes via scan

**Increment 3 (suivi et pilotage) :**
- 10.5 + 10.6
- Le runner a un tableau de bord et peut relancer
- Valeur livree : le cycle de paiement est entierement gere

---

## 8. Risques et mitigations

### 8.1 Risques techniques

| Risque | Probabilite | Impact | Mitigation |
|--------|-------------|--------|------------|
| Rate limiting Slack sur l'envoi de DMs (10+ messages simultanement) | Moyenne | Moyen | Envoi asynchrone (job queued) avec delai entre chaque DM (1-2 secondes). Retry automatique existant. |
| Upload de fichier Slack echoue (QR code) | Faible | Faible | Fallback : le DM est envoye sans QR code, l'IBAN en texte reste disponible. |
| Deprecation de l'API Slack `files.upload` | Certaine | Moyen | Utiliser directement la nouvelle sequence `files.getUploadURLExternal` + `files.completeUploadExternal`. |
| Performance de la generation QR (si beaucoup de commandes) | Faible | Faible | Generation en arriere-plan (job queued). Un QR prend < 100ms a generer. |
| SQLite et chiffrement `encrypted` cast | Faible | Moyen | Le cast `encrypted` de Laravel fonctionne independamment du moteur de BDD. Deja utilise pour `bot_token`. |

### 8.2 Risques UX

| Risque | Probabilite | Impact | Mitigation |
|--------|-------------|--------|------------|
| Le commandeur recoit un DM inattendu du bot | Moyenne | Moyen | Le DM est clairement identifie comme venant de "Lunch Bot" avec le contexte (restaurant, date). |
| Le commandeur ne sait pas scanner un QR code | Faible | Faible | L'IBAN en texte est toujours present comme alternative. Instructions minimales dans le DM. |
| Le runner oublie de configurer son IBAN | Haute | Moyen | Le bot avertit le runner avant l'envoi et propose de configurer. Les DMs sont envoyes quand meme (sans QR/IBAN). |
| Abus du systeme "J'ai paye" (declaration sans paiement) | Faible | Moyen | Contexte de confiance entre collegues. Le runner peut contacter directement en cas de doute. Le statut "Probleme" permet un signalement. |
| Fatigue de notification (trop de DMs) | Moyenne | Moyen | 1 DM initial + max 1 rappel/jour. Pas de notification automatique non sollicitee. |

### 8.3 Risques securite

| Risque | Probabilite | Impact | Mitigation |
|--------|-------------|--------|------------|
| Fuite d'IBAN via la base de donnees | Faible | Eleve | Chiffrement au repos via `encrypted` cast. L'IBAN est inutilisable sans `APP_KEY`. |
| IBAN visible dans les logs Slack | Faible | Moyen | Ne jamais logger l'IBAN. Les logs applicatifs ne contiennent que les IDs. |
| Acces non autorise a un UserPaymentProfile | Faible | Eleve | Scope `BelongsToOrganization` + verification que `provider_user_id` correspond a l'utilisateur authentifie. |
| QR code avec IBAN incorrect | Faible | Moyen | Validation IBAN a la saisie (checksum mod97). L'utilisateur voit son IBAN dans le modal avant enregistrement. |

---

## 9. Criteres de succes

### 9.1 KPIs fonctionnels

| Metrique | Definition | Cible Phase 1b |
|----------|-----------|----------------|
| Taux d'adoption IBAN | % de runners ayant configure leur IBAN | > 70% des runners reguliers |
| Taux d'envoi reussi | % de DMs de paiement effectivement delivres | > 95% |
| Taux de paiement declare | % de PaymentRequests passes en `paid` | > 80% en 48h |
| Temps median de paiement | Temps entre l'envoi du DM et le clic "J'ai paye" | < 2 heures |
| Taux de scan QR | % de commandeurs qui scannent le QR code | Non mesurable directement (a estimer via feedback) |
| Taux de relance | % de PaymentRequests necessitant une relance | < 30% |
| Taux de litige | % de PaymentRequests en statut `disputed` | < 5% |

### 9.2 KPIs d'impact

| Metrique | Definition | Cible |
|----------|-----------|-------|
| Volontariat runner | Evolution du nombre de volontaires runner apres deploiement | Stable ou en hausse (+10%) |
| Satisfaction runner | Feedback qualitatif des runners sur le flux de paiement | Positif ("c'est plus simple qu'avant") |
| Retention globale | Impact sur la retention J7 du bot | +5 points (effet indirect) |

### 9.3 Critere de validation

La Phase 1b est consideree comme un succes quand :

1. Au moins 3 sessions cloturees ont utilise le flux de paiement complet
2. Le taux de paiement declare depasse 70% dans les 48h suivant l'envoi
3. Au moins 1 runner donne un feedback positif spontane sur le flux
4. Aucun incident de securite lie aux IBANs

---

## 10. Estimation de l'effort

### 10.1 Tailles par story

| Story | Description | Taille | Justification |
|-------|-------------|--------|---------------|
| 10.7 | Configuration IBAN | M | Modal Slack, validation IBAN, chiffrement, CRUD, migration |
| 10.1 | DM individuel de paiement | L | Nouvelle Action, job queued, construction DM, gestion erreurs, integration dashboard |
| 10.2 | IBAN dans le DM | S | Lecture du profil, formatage, enrichissement du DM existant |
| 10.3 | QR Code SEPA | L | Nouvelle librairie, generation EPC, upload fichier Slack (nouvelle API), tests |
| 10.4 | Bouton "J'ai paye" | M | Interaction handler, mise a jour DM, notification runner, idempotence |
| 10.5 | Tableau de suivi runner | M | Modal Slack, agregation donnees, blocks dynamiques, bouton relance |
| 10.6 | Relance des paiements | S | Job queued, verification delai 24h, re-envoi DM |

### 10.2 Synthese

| Taille | Nombre de stories | Effort estime (jours dev) |
|--------|-------------------|---------------------------|
| S (Small) | 2 | 2-3 jours |
| M (Medium) | 3 | 6-9 jours |
| L (Large) | 2 | 6-10 jours |
| **Total** | **7 stories** | **14-22 jours dev** |

### 10.3 Elements transverses (inclus dans l'estimation)

- Migrations de base de donnees (2 tables, 1 enum)
- Tests unitaires et feature tests (couverture Actions, Handlers, EpcQrCodeGenerator)
- Nouveaux SlackAction (6-8 nouvelles valeurs dans l'enum)
- Nouveau Handler : `PaymentInteractionHandler`
- Nouvelles Actions : 5-6 Actions dans `app/Actions/Payment/`
- Documentation du format EPC dans le code
- Mise a jour du `SlackBlockBuilder` (blocks pour DM de paiement, tracker, modal IBAN)
- Tests E2E (si le scope le permet)

---

## 11. Hors perimetre Phase 1b

Les elements suivants sont explicitement exclus de cette phase :

| Element | Raison | Phase envisagee |
|---------|--------|-----------------|
| Balance courante entre utilisateurs (compensation) | Complexite, necessite un historique plus long | Phase 2 (story 10.8) |
| Integration Payconiq / Bancontact | Standard different de EPC, API tierces | Phase 3+ |
| Rappels automatiques (sans action du runner) | Risque de spam, le runner doit decider | Phase 2 (eventuellement) |
| Export comptable des paiements | Hors scope meta-contexte | Hors scope |
| Verification bancaire des paiements | Transformerait le bot en service financier | Hors scope definitivement |
| Application du flux de paiement aux Quick Runs | Architecture similaire, les stories 10.1-10.7 couvrent les sessions dejeuner ; l'extension aux Quick Runs sera faite dans un second temps | Phase 1b+ (extension) |

---

## 12. Synthese

La Phase 1b transforme Lunch Bot d'un outil de **coordination** en un outil de **coordination complete**. En ajoutant le dernier maillon de la chaine (la facilitation de paiement), le bot ferme le cycle dejeuner et supprime la derniere friction majeure identifiee dans l'audit business.

Le systeme reste fidele aux principes du meta-contexte : il ne touche pas a l'argent, il facilite l'information. Le QR code SEPA est le raccourci qui reduit le paiement a un geste de 10 secondes. Le systeme declaratif respecte la confiance entre collegues.

L'effort estime (14-22 jours) est significatif mais justifie par l'impact attendu sur la retention et la satisfaction runner. C'est la feature qui transforme un outil "pratique" en un outil "indispensable".
