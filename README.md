# Ardoise — gestion scolaire multi-écoles

Application **Laravel 13 + Vue 3** (SPA servie par Laravel) pour les écoles : notes et bulletins, absences, emplois du temps, devoirs, frais et paiements, communication avec les familles.

- **Une base de données par école** (multi-tenant maison, sans package) + une base centrale.
- **Quatre espaces** : administration de l'école, enseignant, parent (mobile d'abord), console plateforme (superadmin).
- **Parents sans mot de passe** : chaque parent reçoit un code d'accès ; en le saisissant, le serveur retrouve **l'école et le parent**.

---

## Sommaire

1. [Installation](#1-installation)
2. [Comptes de démonstration](#2-comptes-de-démonstration)
3. [Architecture multi-tenant](#3-architecture-multi-tenant)
4. [Authentification](#4-authentification)
5. [Connexion des parents par code](#5-connexion-des-parents-par-code)
6. [Rôles et permissions](#6-rôles-et-permissions)
7. [Règles métier](#7-règles-métier)
8. [API](#8-api)
9. [Frontend](#9-frontend)
10. [Commandes et tâches planifiées](#10-commandes-et-tâches-planifiées)
    - [Abonnements, plans et FedaPay](#abonnements-plans-et-fedapay)
    - [Notifications en file d'attente](#notifications-en-file-dattente)
11. [Tests](#11-tests)
12. [Déploiement](#12-déploiement)

---

## 1. Installation

Prérequis : PHP 8.3+, Composer, Node 20+, SQLite (développement) ou MySQL 8 (production).

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed          # base centrale : plans + superadmin
php artisan ardoise:demo --fresh    # école de démonstration « palmiers » + données
npm install
npm run build                       # ou : npm run dev
php artisan serve
php artisan queue:work              # dans un 2e terminal : e-mails et notifications
```

`composer run dev` lance le serveur, la file d'attente et Vite en une commande.

Ouvrez **http://palmiers.localhost:8000** (les sous-domaines `*.localhost` fonctionnent sans configuration dans Chrome et Firefox). Sur cette adresse, connectez-vous en tant qu'établissement pour conserver la session sur le sous-domaine de l'école. Depuis **http://localhost:8000**, la page de connexion demande le *code établissement* (`palmiers`) aux membres du personnel ; cette session reste sur le domaine central. Les parents n'ont besoin que de leur code.

> La clé `APP_KEY` sert aussi à calculer l'empreinte des codes parents : la changer invalide tous les codes (il faut alors les régénérer).

## 2. Comptes de démonstration

Créés par `php artisan ardoise:demo` (la commande les réaffiche à la fin) :

| Profil | Identifiant | Mot de passe |
|---|---|---|
| Administrateur de l'école | `admin@lespalmiers.ci` | `password` |
| Enseignant (M. Diallo) | `m.diallo@lespalmiers.ci` | `password` |
| Superadmin (console plateforme, `localhost`) | `SUPERADMIN_EMAIL` | `SUPERADMIN_PASSWORD` |
| Parents | 5 codes d'accès générés, affichés par la commande (ex. Mariam Traoré : Awa en 5e B, Kofi en CM2 A) | — |

Les codes sont aussi visibles et imprimables dans **Administration → Parents → fiche du parent**.

## 3. Architecture multi-tenant

```
Base centrale (connexion « central »)          Base de chaque école (connexion « tenant »)
├─ tenants, domains, plans, subscriptions       ├─ users, roles, permissions, personal_access_tokens
├─ parent_access_codes (empreinte → école)      ├─ campuses, academic_years, terms, levels, classes
├─ platform_admins, support_tickets             ├─ students, enrollments, parent_profiles
└─ usage_snapshots, platform_settings           ├─ assessments, grades, report_cards, attendances
                                                └─ fees, fee_assignments, payments, documents…
```

- `app/Tenancy/TenantManager` connecte la connexion `tenant` à la base de l'école (`tenant_{code}` ; en SQLite `database/tenants/tenant_{code}.sqlite`) et purge le cache de connexion.
- `app/Tenancy/TenantResolver` détermine l'école **côté serveur uniquement**, dans cet ordre :
  1. le domaine : `{code}.{TENANCY_BASE_DOMAIN}` ou un domaine personnalisé (`domains`) ;
  2. sur le domaine central, le jeton mobile préfixé `t_{code}.` ;
  3. sur le domaine central, l'école mémorisée en session lors de la connexion.
- Le client n'envoie **jamais** d'identifiant d'école. Une session ouverte pour une école ne peut pas être réutilisée sur une autre (`EnsureUserBelongsToTenant`).
- Création d'une école (`App\Actions\CreateTenant`, console plateforme ou `php artisan tenant:create`) : enregistrement central → création de la base → migrations `database/tenant/migrations` → rôles, permissions, niveaux, année scolaire → compte administrateur. En cas d'échec, tout est annulé.
- Depuis la console plateforme, le code et le sous-domaine sont générés depuis le nom de l'école ; si le sous-domaine existe déjà, un suffixe numérique est ajouté. La commande `tenant:create` continue d'utiliser le code fourni en argument.
- MySQL : option `TENANCY_CREATE_DB_USERS=true` pour un utilisateur MySQL dédié par école (mot de passe chiffré dans `tenants.db_password`).

Middleware (alias dans `bootstrap/app.php`) : `tenant` (identifie l'école, obligatoire ou `tenant:optional`), `tenant.active` (abonnement suspendu/expiré → 423), `tenant.user`, `central`, `role:…`.

## 4. Authentification

Laravel Sanctum, deux modes :

| Client | Mécanisme |
|---|---|
| SPA (navigateur) | Cookies de session + CSRF (`/sanctum/csrf-cookie`), aucun jeton stocké côté navigateur |
| Application mobile | Jeton Bearer `t_{code}.{id}|{secret}` (180 jours, révocable) |

- Personnel et enseignants : `POST /api/v1/auth/login { login (e-mail ou téléphone), password, school_code? }`.
- Mot de passe oublié : lien par e-mail, ou code à 6 chiffres par SMS si l'identifiant est un téléphone (`/auth/forgot-password`, `/auth/reset-password`).
- Superadmin : garde `platform`, uniquement sur le domaine central (`/api/v1/platform/auth/*`).
- **Mot de passe provisoire** : l'administrateur créé par la plateforme (ou dont les identifiants sont renvoyés) doit choisir son mot de passe à la première connexion. Tant qu'il ne l'a pas fait, l'API répond `403 { code: "password_change_required" }` (sauf `auth/me`, `auth/logout`, `profile/password`) et l'interface ouvre **/security**.
- **Double authentification (TOTP)** : personnel, enseignants et superadmins l'activent dans **Sécurité du compte** (QR code pour Google Authenticator, Microsoft Authenticator, Authy… + 8 codes de secours à usage unique). À la connexion, `auth/login` renvoie alors `{ two_factor: true, challenge }` au lieu de la session ; le client envoie ensuite `POST auth/two-factor { challenge, code | recovery_code }` (`platform/auth/two-factor` pour la console). Un défi vaut 10 minutes et 5 essais ; un code déjà utilisé est refusé. La console peut la rendre **obligatoire** (Paramètres → Sécurité) pour les superadmins et/ou pour les administrateurs et directeurs d'école : `403 { code: "two_factor_setup_required" }` tant qu'elle n'est pas activée.

## 5. Connexion des parents par code

1. À l'inscription d'un élève (ou à la création d'un parent), l'école génère un code de 12 caractères, ex. `K7QM-4XTR-9HCP` (alphabet sans caractères ambigus : pas de 0/O, 1/I/L).
2. La base **centrale** stocke uniquement l'**empreinte HMAC-SHA256** du code (clé : `APP_KEY`), les 4 derniers caractères, l'école et le parent. La base de l'école conserve le code **chiffré** pour pouvoir réimprimer la fiche.
3. Dans l'application, le parent saisit seulement son code :

```http
POST /api/v1/parent/login
{ "code": "k7qm4xtr9hcp", "device_name": "iPhone de Mariam" }
```

Le serveur normalise la saisie, retrouve l'empreinte, **en déduit l'école et le parent**, vérifie que l'école et le compte sont actifs, puis :
- navigateur : ouvre une session liée à l'école ;
- application mobile : renvoie `{ token: "t_palmiers.12|…", token_type: "Bearer", data: { …profil, tenant } }`.

4. Toutes les requêtes suivantes (`/parent/children`, `/report-cards/{élève}`…) sont servies depuis la base de cette école, limitées aux enfants du parent.

Sécurité : 5 tentatives/minute et 30/heure par IP ; « Nouveau code » révoque l'ancien et déconnecte tous les appareils du parent ; « Désactiver l'accès » bloque le code sans le supprimer ; sur un sous-domaine, un code d'une autre école est refusé.

## 6. Rôles et permissions

RBAC maison dans la base de chaque école (`roles`, `permissions`, `permission_role`, `role_user`) — permissions `ressource.action` (`students.create`, `grades.publish`…), catalogue dans `app/Support/PermissionCatalog.php`.

| Rôle | Périmètre par défaut |
|---|---|
| `school_admin` | Tout (`*`), rôle non modifiable |
| `director` | Pilotage, validation des notes, bulletins, journal d'activité |
| `academic_manager` | Évaluations, notes, emplois du temps |
| `accountant` | Frais, paiements, reçus |
| `secretary` | Inscriptions, parents, documents |
| `teacher` | Ses classes, évaluations, notes, appel, devoirs |
| `parent` | Ses enfants uniquement (espace parent) |

`Gate::before` accorde une capacité si l'utilisateur possède la permission ; la matrice est modifiable dans **Administration → Rôles et permissions**. Les enseignants sont en plus limités à leurs classes côté serveur.

**Journal d'activité** (`activity_log.view`, **Administration → Journal d'activité**) : connexions, sécurité des comptes, inscriptions, notes, bulletins, paiements, utilisateurs, paramètres, imports — avec l'auteur, l'objet, les détails et l'adresse IP ; filtrable, exportable.

## 7. Règles métier

- Notes ramenées sur 20, moyenne de matière pondérée par le coefficient de l'évaluation ; un élève absent n'est pas compté.
- Moyenne générale pondérée par le coefficient de la matière dans la classe ; rang olympique (ex æquo = même rang) ; arrondi paramétrable.
- Valider une évaluation exige une note ou une absence pour chaque élève, la **verrouille** et la rend visible aux parents ; seul le personnel autorisé peut la déverrouiller (action journalisée).
- Les parents ne voient que les notes validées et les bulletins publiés.
- Frais : échéances générées par élève (montant réparti, le reste sur la dernière) ; un encaissement est affecté aux échéances les plus anciennes ; « en retard » est calculé.
- Une seule année scolaire active ; une année clôturée n'accepte plus de notes ni d'absences.
- Notifications : in-app (toujours), e-mail et SMS selon les paramètres de l'école ; SMS seulement pour les messages urgents (absence, bulletin).

## 8. API

Préfixe `/api/v1`, réponses JSON. Listes : `?search=&page=&per_page=&sort=-champ&filter[clé]=valeur` → `{ data: [...], meta: { current_page, per_page, total, last_page } }`. Erreurs : `422 { message, errors }`, `401`, `403`, `404`, `423` (école suspendue), `429`.

| Domaine | Routes principales |
|---|---|
| Public | `GET public/plans`, `POST public/demo-requests`, `POST newsletter` |
| Connexion | `POST auth/login`, `POST parent/login`, `POST auth/forgot-password`, `POST auth/reset-password`, `GET auth/me`, `POST auth/logout` |
| Référentiels | `GET options`, CRUD `schools`, `academic-years` (+`set_active`, `close`), `classes` (+`subjects`), `subjects`, `teachers` |
| Scolarité | CRUD `students` (+`transfer`, `archive`, `change-class`, `restore`, `bulk/{action}`), CRUD `parents` (+`regenerate-code`, `toggle`) |
| Pédagogie | CRUD `assessments`, `GET/PUT assessments/{id}/grades`, `POST …/validate`, `POST …/unlock`, `report-cards` (+`generate`, `publish`, `{élève}`, `{élève}/pdf`), `attendance/session`, `attendance`, `attendance/{id}/justify`, `timetables`, `timetables/entries`, CRUD `homework` |
| Finances | CRUD `fees`, `payments` (échéances), `POST payments`, `payments/summary`, `payments/records/{id}/confirm|cancel`, `payments/{id}/receipt` |
| Communication | CRUD `announcements`, `notifications` (+`read`, `read-all`), `documents`, `support-tickets` |
| Administration | CRUD `users`, `roles` (+`permissions`), `GET permissions`, `settings/{section}`, `activity-log` |
| Import | `GET imports/{students\|payments}/template`, `POST imports/students`, `POST imports/payments` (`file`, `dry_run`, `notify`), `GET assessments/{id}/grades/template`, `POST assessments/{id}/grades/import` |
| Sécurité | `PUT profile/password`, `profile/two-factor` (`GET`, `POST setup`, `POST confirm`, `POST recovery-codes`, `DELETE`), `POST auth/two-factor` ; console : `platform/auth/password`, `platform/auth/two-factor/*` |
| Abonnement | `billing`, `billing/checkout`, `billing/verify`, `billing/payments/{id}/invoice` |
| Enseignant | `teacher/dashboard`, `teacher/classes` |
| Parent | `parent/children`, `parent/children/{id}`, `parent/announcements`, `parent/documents`, `parent/document-requests`, `parent/timetable`, `parent/attendance/{id}/justify`, `parent/homework/{id}/done`, `parent/payments/{id}/checkout` |
| Plateforme | `platform/dashboard`, `platform/demo-requests`, `platform/tenants` (+`suspend`, `activate`, `stats`), `platform/plans`, `platform/subscriptions`, `platform/payments` (+`{id}/invoice`), `platform/notifications`, `platform/usage`, `platform/schools`, `platform/users`, `platform/tickets`, `platform/announcements`, `platform/settings/{section}` |

### Import et export

- **Export** : chaque liste (élèves, paiements, journal, tenants…) a un bouton **Exporter** qui télécharge toutes les lignes filtrées en CSV pour Excel (UTF-8, séparateur `;`). Élèves et paiements exigent la permission `…export`.
- **Import** (Excel `.xlsx` ou CSV, 5 000 lignes max.) : modèle téléchargeable, bouton **Vérifier** (`dry_run`, rien n'est enregistré), puis **Importer**. Tout le fichier est contrôlé d'abord : à la moindre erreur, l'import est refusé (`422 { lines: [{ line, message }] }`) et rien n'est enregistré.
  - *Élèves* : matricule connu → fiche mise à jour (et changement de classe), sinon inscription ; classe cherchée par son nom dans l'année en cours ; parent retrouvé par téléphone ou créé avec son code d'accès ; limite du plan respectée.
  - *Notes* (écran **Saisie des notes**) : feuille pré-remplie avec les élèves de la classe ; une ligne vide conserve la note existante ; les notes restent en brouillon jusqu'à la validation.
  - *Paiements* : réparti sur les échéances les plus anciennes, refusé au-delà du reste dû ; parents prévenus seulement si la case est cochée.

Liste complète : `php artisan route:list --path=api`.

## 9. Frontend

`resources/js` — Vue 3 (`<script setup>`), Vue Router, Pinia, Axios, Tailwind CSS v4.

```
config/resources.js    déclaration des ressources CRUD (colonnes, filtres, formulaires) → pages génériques
config/navigation.js   menus par espace (entrées masquées selon les permissions)
services/http.js       client Axios (cookies Sanctum, CSRF, erreurs Laravel normalisées)
services/api.js        appels métier ; services/resource.js : CRUD générique
stores/                auth (session, rôles, permissions), options (listes de référence), ui (toasts, confirmations)
layouts/               Public, Auth, SchoolAdmin, Teacher, Parent (barre d'onglets mobile), SuperAdmin
pages/                 public, generic (liste / formulaire / fiche), admin, teacher, parent, superadmin, shared
```

Le routeur protège chaque espace (rôle) et chaque page (permission). Les permissions côté interface ne servent qu'à l'affichage : le serveur vérifie tout.

## 10. Commandes et tâches planifiées

| Commande | Rôle |
|---|---|
| `php artisan tenant:create {code} {nom} --admin-email=…` | Crée une école complète |
| `php artisan tenants:list` | Liste les écoles |
| `php artisan tenants:migrate [--tenant=code] [--fresh] [--seed]` | Migre les bases des écoles |
| `php artisan ardoise:demo [--fresh]` | École de démonstration |
| `platform:collect-usage` | Statistiques d'usage (quotidien, 02:00) |
| `subscriptions:check` | Statut des abonnements + rappels à l'administrateur (J-7, J-3, J-1, délai de grâce, blocage) — quotidien, 08:00 |
| `payments:sync-fedapay` | Relit auprès de FedaPay les paiements restés « en attente » (webhook perdu) — toutes les 15 min |
| `tenants:prune-tokens` | Purge des jetons expirés |
| `backup:run [--tenant=code] [--skip-central] [--no-prune]` | Sauvegarde de la base centrale et de chaque école (quotidien, 01:30) |
| `sms:test {numéro}` | Envoie un SMS de test avec le fournisseur configuré |

Planification dans `routes/console.php` : lancer `php artisan schedule:work` (ou la tâche cron `schedule:run`).

### Abonnements, plans et FedaPay

**Création d'une école** (console plateforme ou `tenant:create`) : l'administrateur reçoit par e-mail son identifiant, un mot de passe provisoire, le code établissement et le lien `https://{code}.{TENANCY_BASE_DOMAIN}/login`. L'e-mail passe par la file d'attente, chiffré (il contient un mot de passe). « Renvoyer les identifiants » (liste des tenants) génère un nouveau mot de passe et le renvoie.

**État de l'abonnement** (`Tenant::billingState()`), calculé à chaque requête :

| État | Condition | Accès |
|---|---|---|
| `trial` / `active` | avant la fin de l'essai ou de la période payée | complet ; bandeau à J-7 |
| `grace` | échéance passée depuis moins de `TENANCY_GRACE_DAYS` (15 j) | complet + bandeau rouge |
| `expired` | délai de grâce écoulé | **bloqué** : seul l'administrateur se connecte, et uniquement sur `/admin/billing` pour payer (API : 402 `subscription_expired`) ; parents refusés |
| `suspended` | suspendu ou résilié par la plateforme | aucun accès (423) |

**Plans** : chaque plan coche des modules (`App\Support\PlanFeatures` : notes, absences, espace parent, devoirs, emplois du temps, frais, paiement en ligne, SMS, documents…) et fixe des limites (établissements, élèves actifs, comptes hors parents). Les routes d'un module absent répondent 403 `feature_unavailable` (middleware `feature:…`) et l'interface masque les menus ; une limite atteinte renvoie une erreur 422. `/auth/me` renvoie `features` et `subscription`.

**Factures** : chaque paiement d'abonnement confirmé reçoit un numéro continu par année (`FAC-2026-000001`). La facture (PDF avec dompdf, sinon page imprimable) se télécharge dans **Administration → Abonnement** et **Console → Paiements**, et est jointe à l'e-mail de confirmation quand dompdf est installé. Émetteur, IFU / NIF, RCCM, taux de TVA et mentions : **Console → Paramètres → Facturation**.

**Alertes plateforme** : les superadmins sont prévenus (cloche **Alertes** de la console + e-mail) d'un paiement d'abonnement reçu ou échoué, d'une échéance proche, d'un passage en délai de grâce, d'un blocage, et d'un échec de sauvegarde. Événements, envoi par e-mail et destinataires supplémentaires : **Console → Paramètres → Alertes**. Chaque alerte d'échéance n'est envoyée qu'une fois par école.

**FedaPay** (API REST v1, sans SDK : `App\Services\Payments\FedaPay\FedaPayClient`) :

- *Abonnements* — compte de la plateforme, clés dans `.env` (`FEDAPAY_*`). L'administrateur choisit plan et durée dans **Administration → Abonnement**, paie sur la page FedaPay, puis revient sur `/admin/billing?payment=…` (vérification immédiate). Dans FedaPay, déclarer le webhook `{APP_URL}/api/v1/webhooks/fedapay` (événements `transaction.*`) et copier son secret dans `FEDAPAY_WEBHOOK_SECRET`. Paiement approuvé → abonnement prolongé à partir de la fin en cours, plan appliqué, reçu envoyé par e-mail.
- *Frais scolaires* — compte FedaPay **de l'école** : clés saisies dans **Paramètres → Paiement en ligne** (chiffrées en base, jamais réaffichées). Webhook à déclarer : `{APP_URL}/api/v1/webhooks/fedapay/{code-école}`. Sans FedaPay, le paiement parent reste une demande que la comptabilité confirme.
- Sécurité : signature `X-FEDAPAY-SIGNATURE` vérifiée (HMAC-SHA256, tolérance 5 min), puis statut **relu auprès de l'API** ; montant contrôlé ; traitement idempotent.

### Notifications en file d'attente

Toutes les notifications (`SchoolNotification`) et les e-mails de la plateforme partent dans la file `database` (table `jobs` de la base centrale). Chaque tâche emporte l'identifiant de l'école (`App\Tenancy\QueueTenancy`) : le worker reconnecte la bonne base avant d'envoyer. Un worker doit tourner en production : `php artisan queue:work --tries=3` (Supervisor, systemd…).

**Qui reçoit quoi** se règle par école dans **Paramètres → Destinataires** : pour chaque événement (absence, justificatif, notes validées, bulletin, devoir, paiement encaissé, paiement en ligne, demande de document), on coche les destinataires (parents de l'élève, professeur principal, enseignants de la classe, administrateurs, direction, comptabilité, secrétariat…) et les canaux (application, e-mail, SMS — SMS seulement si le plan l'inclut). Les annonces gardent leurs destinataires choisis à la publication. Catalogue et valeurs par défaut : `App\Support\NotificationEvents`.

## 11. Tests

```bash
php artisan test
```

- `tests/Unit/GradeCalculatorTest.php` : moyennes pondérées, absences, rang ex æquo, arrondi, saisie des notes.
- `tests/Unit/AccessCodeTest.php` : génération, format, normalisation, absence de collisions.
- `tests/Feature/ParentCodeLoginTest.php` : crée une vraie base d'école, connecte un parent par son seul code, vérifie le jeton préfixé, l'accès à ses données, le refus des routes du personnel et l'invalidation d'un ancien code.
- `tests/Feature/SubscriptionAccessTest.php` : délai de grâce, blocage après expiration (seul l'administrateur, seulement l'abonnement), suspension, modules et limites du plan.
- `tests/Feature/TenantCredentialsMailTest.php` : e-mail d'identifiants à la création, renvoi avec nouveau mot de passe.
- `tests/Feature/FedaPaySubscriptionTest.php`, `FedaPaySchoolFeesTest.php` : paiement FedaPay (API simulée), webhooks signés, prolongation, clés d'école chiffrées.
- `tests/Feature/NotificationRulesTest.php` : destinataires réglables, SMS selon le plan, livraison par la file dans la bonne base d'école.
- `tests/Feature/SubscriptionRemindersTest.php` : rappels de renouvellement envoyés une seule fois.
- `tests/Feature/AccountSecurityTest.php` : mot de passe provisoire, double authentification (activation, connexion en deux étapes, codes de secours, rejeu, obligation), vecteurs RFC 6238.
- `tests/Feature/ImportExportTest.php` : import d'élèves (CSV et Excel), de notes et de paiements, refus complet d'un fichier en erreur.
- `tests/Feature/ActivityLogTest.php`, `SubscriptionInvoiceAndAlertsTest.php`, `SmsGatewayTest.php`, `DatabaseBackupTest.php` : journal, factures et alertes plateforme, fournisseurs SMS, sauvegardes.

## 12. Déploiement

- MySQL : `DB_CONNECTION=mysql`, `TENANT_DB_CONNECTION=mysql` ; l'utilisateur doit pouvoir `CREATE DATABASE`.
- DNS générique `*.votredomaine` vers le serveur, `TENANCY_BASE_DOMAIN=votredomaine`, `TENANCY_CENTRAL_DOMAINS=votredomaine,www.votredomaine`, `SESSION_DOMAIN=.votredomaine`, `SANCTUM_STATEFUL_DOMAINS=votredomaine,*.votredomaine`.
- `php artisan migrate --force && php artisan tenants:migrate`, `npm run build`, worker de files (`queue:work`), planificateur, `php artisan storage:link`.
- PDF : `composer require barryvdh/laravel-dompdf` pour des bulletins, reçus et factures d'abonnement en PDF ; sans lui, une page HTML imprimable est renvoyée.
- SMS : `SMS_DRIVER` = `orange` (Orange SMS API), `twilio`, `africastalking` ou `http` (passerelle générique configurable : URL, authentification, noms des champs, champs fixes, contrôle de la réponse — voir `.env.example`). `SMS_DEFAULT_COUNTRY_CODE` complète les numéros saisis sans indicatif. Vérifier avec `php artisan sms:test +229…`.
- Sauvegardes : `backup:run` chaque nuit (planificateur) ; SQLite copié à chaud (`VACUUM INTO`), MySQL via `mysqldump --single-transaction` (le binaire doit être installé sur le serveur), PostgreSQL via `pg_dump`. Fichiers `.gz` sur le disque `BACKUP_DISK` (`local` = `storage/app/private/backups` ; préférer un disque distant `s3`), conservés `BACKUP_KEEP_DAYS` jours. Restauration : `gunzip -c tenant_x.sql.gz | mysql tenant_x` (ou remplacer le fichier `.sqlite`).
- Paiement en ligne : renseigner `FEDAPAY_*` (environnement `live` en production), déclarer les webhooks ; sans FedaPay, la passerelle `manual` enregistre une demande que la comptabilité confirme.
- Un worker de file (`queue:work`) et le planificateur (`schedule:run` chaque minute) sont indispensables : e-mails, notifications, rappels et vérification des paiements en dépendent.
