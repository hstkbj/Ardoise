# Déploiement d'Ardoise

## 1. Prérequis

- Un serveur Linux avec un nom de domaine (`votredomaine`) et la possibilité de créer un enregistrement DNS générique.
- Docker 24+ et Docker Compose v2 **ou** : PHP 8.4 (extensions `pdo_mysql`, `intl`, `zip`, `gd`, `bcmath`, `mbstring`, `opcache`), Composer 2, Node 20+, Nginx, Supervisor, MySQL 8.
- `mysqldump` et `gzip` sur la machine qui exécute `php artisan backup:run` (SQLite : copie directe, sans `mysqldump`). L'image Docker les inclut.
- Un utilisateur MySQL autorisé à `CREATE DATABASE` : chaque école a sa propre base (`tenant_{code}`).
- Un compte FedaPay (live) et un serveur SMTP.

## 2. DNS et HTTPS

| Enregistrement | Valeur |
|---|---|
| `votredomaine` | IP du serveur |
| `*.votredomaine` | IP du serveur (wildcard : un sous-domaine par école) |

Le certificat TLS doit couvrir `votredomaine` et `*.votredomaine` (wildcard, validation DNS-01 avec Let's Encrypt par exemple). Terminer le HTTPS sur un reverse proxy (Caddy, Traefik, Nginx) ou directement dans Nginx.

## 3. Variables `.env`

Partir de `.env.example`, puis renseigner :

| Variable | Valeur en production |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_KEY` | `php artisan key:generate`. À sauvegarder : la changer invalide tous les codes parents |
| `APP_URL` | `https://votredomaine` |
| `TENANCY_BASE_DOMAIN` | `votredomaine` |
| `TENANCY_CENTRAL_DOMAINS` | `votredomaine,www.votredomaine` |
| `TENANCY_TRIAL_DAYS`, `TENANCY_GRACE_DAYS`, `BILLING_REMINDER_DAYS` | essai, délai de grâce, rappels (`7,3,1`) |
| `TENANCY_CREATE_DB_USERS` | `true` pour un utilisateur MySQL par école (droit `CREATE USER` requis) |
| `SESSION_DOMAIN` | `.votredomaine` (point initial : session partagée par les sous-domaines) |
| `SANCTUM_STATEFUL_DOMAINS` | `votredomaine,*.votredomaine` |
| `DB_CONNECTION`, `TENANT_DB_CONNECTION` | `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | base centrale (avec Docker : `DB_HOST=mysql`, utilisateur `root`) |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS` | SMTP réel (le mailer `log` n'envoie rien) |
| `QUEUE_CONNECTION` | `database` |
| `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | compte créé par `db:seed` ; mot de passe fort, à changer après la première connexion |
| `SMS_DRIVER` et les variables du fournisseur (`SMS_HTTP_*`, `SMS_ORANGE_*`, `SMS_TWILIO_*`, `SMS_AT_*`) | `log` par défaut (rien n'est envoyé) ; `http`, `orange`, `twilio` ou `africastalking` pour envoyer vraiment |
| `FEDAPAY_ENVIRONMENT` | `live` |
| `FEDAPAY_PUBLIC_KEY`, `FEDAPAY_SECRET_KEY` | clés du compte de la plateforme |
| `FEDAPAY_WEBHOOK_SECRET` | secret du webhook ci-dessous |
| `FEDAPAY_CURRENCY`, `FEDAPAY_COUNTRY` | `XOF`, `bj` (adapter) |
| `BACKUP_DISK`, `BACKUP_PATH` | disque de `config/filesystems.php` et dossier recevant les sauvegardes (idéalement un stockage distinct du serveur, ex. S3) |
| `BACKUP_KEEP_DAYS` | rétention en jours (14 par défaut), appliquée à chaque exécution sauf `--no-prune` |
| `BACKUP_MYSQLDUMP` | chemin du binaire `mysqldump` s'il n'est pas dans le `PATH` |

### Webhooks FedaPay

- Abonnements (compte plateforme) : `{APP_URL}/api/v1/webhooks/fedapay`, événements `transaction.*`.
- Frais scolaires (compte de chaque école) : `{APP_URL}/api/v1/webhooks/fedapay/{code-école}`. Les clés de l'école se saisissent dans Administration > Paramètres > Paiement en ligne.

## 4. Déploiement Docker

```bash
git clone <depot> ardoise && cd ardoise
cp .env.example .env            # renseigner la section 3 (DB_HOST=mysql est imposé par le compose)
docker compose up -d --build
```

Services : `app` (Nginx + PHP-FPM sur le port 8080, `APP_PORT` pour le changer), `queue` (`queue:work --tries=3 --max-time=3600`), `scheduler` (`schedule:work`), `mysql` (volume `mysql`). Le volume `storage` est partagé par `app`, `queue` et `scheduler`. Placer un reverse proxy HTTPS devant le port 8080.

Initialisation : voir la section 6.

## 5. Déploiement classique

1. Code dans `/var/www/ardoise`, propriétaire `www-data`.
2. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
3. Nginx : adapter `docker/nginx/ardoise.conf` (`listen 443 ssl`, `root /var/www/ardoise/public`, `fastcgi_pass unix:/run/php/php8.4-fpm.sock`).
4. Supervisor : copier `docker/supervisor/ardoise-worker.conf` dans `/etc/supervisor/conf.d/`, adapter les chemins, puis `supervisorctl reread && supervisorctl update`.
5. Planificateur : une seule des deux options.
   - cron (`crontab -u www-data -e`) : `* * * * * cd /var/www/ardoise && php artisan schedule:run >> /dev/null 2>&1`
   - ou `docker/supervisor/ardoise-scheduler.conf` (`schedule:work`).
6. Optimisations : `php artisan config:cache route:cache view:cache event:cache`.

## 6. Première mise en production

Docker : préfixer chaque commande par `docker compose exec app`.

```bash
php artisan key:generate --force   # seulement si APP_KEY est vide
php artisan migrate --force        # base centrale
php artisan db:seed --force        # plans + superadmin (SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD)
php artisan tenants:migrate        # bases d'écoles existantes (aucune au tout début)
php artisan storage:link --force
```

Ensuite :

1. Se connecter à `https://votredomaine` avec le superadmin, changer le mot de passe et activer la double authentification (TOTP).
2. Vérifier les plans, puis créer la première école depuis la console plateforme (ou `php artisan tenant:create {code} "{Nom}" --admin-email=…`). L'administrateur reçoit ses identifiants par e-mail via la file d'attente : vérifier que le worker tourne.
3. Déclarer les webhooks FedaPay (section 3) et tester un paiement sandbox avant de passer en `live`.

## 7. Sauvegardes et restauration

```bash
php artisan backup:run                       # base centrale + toutes les écoles, puis purge des anciennes sauvegardes
php artisan backup:run --tenant=palmiers     # une ou plusieurs écoles (option répétable)
php artisan backup:run --skip-central        # écoles seulement
php artisan backup:run --no-prune            # conserve les anciennes sauvegardes
```

Chaque base produit un fichier `.sql.gz` sur le disque `BACKUP_DISK`, dans `BACKUP_PATH`. La commande est **déjà planifiée chaque nuit à 01:30** (`routes/console.php`) : il suffit que le planificateur tourne (service `scheduler` Docker, cron ou Supervisor). Un échec alerte les superadmins. Copier aussi hors du serveur `APP_KEY` et `.env` : sans la clé, les codes parents et les clés FedaPay chiffrées des écoles sont illisibles.

### Restauration

1. Récupérer le fichier depuis le disque de sauvegarde.
2. Mettre l'application en maintenance : `php artisan down`.
3. Restaurer (la base cible doit exister) :

```bash
gunzip -c fichier.sql.gz | mysql -h HOTE -u UTILISATEUR -p NOM_DE_LA_BASE
```

Base centrale : `DB_DATABASE`. École : `tenant_{code}`. Avec Docker : `gunzip -c fichier.sql.gz | docker compose exec -T mysql mysql -uroot -p"$DB_PASSWORD" tenant_palmiers`.

4. `php artisan tenants:migrate` si la sauvegarde précède une migration, puis `php artisan up`.

Tester la restauration sur une base vide au moins une fois par trimestre.

## 8. Mise à jour

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan tenants:migrate
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

Avec Docker : `git pull && docker compose up -d --build`, puis `docker compose exec app php artisan migrate --force` et `docker compose exec app php artisan tenants:migrate` (les workers sont recréés par le build). Lancer `backup:run` avant chaque mise à jour.

## 9. Supervision

- Logs applicatifs : `storage/logs/laravel.log` (Docker : `docker compose logs -f app queue scheduler`).
- Tâches en échec : `php artisan queue:failed`, relancer avec `php artisan queue:retry all`, purger avec `php artisan queue:flush`.
- Santé : `GET /up` répond 200 (utilisé par le healthcheck Docker).
- Vérifier régulièrement : worker actif (`supervisorctl status`), dernière exécution du planificateur, taille de la table `jobs`, espace disque, dernière sauvegarde.

## 10. Checklist de sécurité

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] HTTPS partout (HSTS activé dans Nginx), certificat wildcard renouvelé automatiquement
- [ ] Double authentification TOTP activée sur le compte superadmin (conseillée aussi pour le personnel des écoles)
- [ ] Mot de passe superadmin fort, différent de celui par défaut
- [ ] `APP_KEY` sauvegardée hors serveur et jamais modifiée
- [ ] `.env` non lisible par le web (`chmod 600`), absent de l'image et du dépôt
- [ ] `storage` et `bootstrap/cache` accessibles en écriture à l'utilisateur PHP uniquement ; seul `public/` est exposé par Nginx
- [ ] MySQL non exposé sur Internet (pas de `ports:` sur le service `mysql`)
- [ ] Sauvegardes quotidiennes, copie hors serveur, restauration testée
- [ ] Webhooks FedaPay avec secret renseigné, `FEDAPAY_ENVIRONMENT=live` seulement après les tests
- [ ] Pare-feu : seuls 80 et 443 (et SSH restreint) ouverts
