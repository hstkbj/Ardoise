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
```

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
| `director` | Pilotage, validation des notes, bulletins |
| `academic_manager` | Évaluations, notes, emplois du temps |
| `accountant` | Frais, paiements, reçus |
| `secretary` | Inscriptions, parents, documents |
| `teacher` | Ses classes, évaluations, notes, appel, devoirs |
| `parent` | Ses enfants uniquement (espace parent) |

`Gate::before` accorde une capacité si l'utilisateur possède la permission ; la matrice est modifiable dans **Administration → Rôles et permissions**. Les enseignants sont en plus limités à leurs classes côté serveur.

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
| Administration | CRUD `users`, `roles` (+`permissions`), `GET permissions`, `settings/{section}` |
| Enseignant | `teacher/dashboard`, `teacher/classes` |
| Parent | `parent/children`, `parent/children/{id}`, `parent/announcements`, `parent/documents`, `parent/document-requests`, `parent/timetable`, `parent/attendance/{id}/justify`, `parent/homework/{id}/done`, `parent/payments/{id}/checkout` |
| Plateforme | `platform/dashboard`, `platform/tenants` (+`suspend`, `activate`, `stats`), `platform/plans`, `platform/subscriptions`, `platform/payments`, `platform/usage`, `platform/schools`, `platform/users`, `platform/tickets`, `platform/announcements`, `platform/settings/{section}` |

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
| `subscriptions:check` | Essais / abonnements expirés → suspension après le délai de grâce |
| `tenants:prune-tokens` | Purge des jetons expirés |

Planification dans `routes/console.php` : lancer `php artisan schedule:work` (ou la tâche cron `schedule:run`).

## 11. Tests

```bash
php artisan test
```

- `tests/Unit/GradeCalculatorTest.php` : moyennes pondérées, absences, rang ex æquo, arrondi, saisie des notes.
- `tests/Unit/AccessCodeTest.php` : génération, format, normalisation, absence de collisions.
- `tests/Feature/ParentCodeLoginTest.php` : crée une vraie base d'école, connecte un parent par son seul code, vérifie le jeton préfixé, l'accès à ses données, le refus des routes du personnel et l'invalidation d'un ancien code.

## 12. Déploiement

- MySQL : `DB_CONNECTION=mysql`, `TENANT_DB_CONNECTION=mysql` ; l'utilisateur doit pouvoir `CREATE DATABASE`.
- DNS générique `*.votredomaine` vers le serveur, `TENANCY_BASE_DOMAIN=votredomaine`, `TENANCY_CENTRAL_DOMAINS=votredomaine,www.votredomaine`, `SESSION_DOMAIN=.votredomaine`, `SANCTUM_STATEFUL_DOMAINS=votredomaine,*.votredomaine`.
- `php artisan migrate --force && php artisan tenants:migrate`, `npm run build`, worker de files (`queue:work`), planificateur, `php artisan storage:link`.
- PDF : installer `barryvdh/laravel-dompdf` pour des bulletins et reçus PDF ; sans lui, une page HTML imprimable est renvoyée.
- SMS : `SMS_DRIVER=http` + `SMS_HTTP_URL` / `SMS_HTTP_TOKEN` (adapter `App\Services\Sms\HttpSmsGateway` au fournisseur).
- Paiement en ligne : la passerelle `manual` enregistre une demande que la comptabilité confirme ; brancher un opérateur en implémentant `App\Services\Payments\PaymentGateway`.
