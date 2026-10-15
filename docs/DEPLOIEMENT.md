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

**Adresses.** Le domaine central (`votredomaine`) ne sert qu'au superadmin et au site public. Chaque école se connecte uniquement depuis **son** adresse, `{code}.votredomaine` (ou un domaine personnalisé déclaré pour l'école) : le personnel et les enseignants ne peuvent pas se connecter depuis le domaine central. Les parents n'utilisent pas le web : ils se connectent avec leur code dans l'application mobile (`POST /api/v1/parent/login`, documentation sur `/api/docs`).

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
| `SESSION_DOMAIN` | vide : chaque adresse (domaine central, école) garde sa propre session |
| `TRUSTED_PROXIES` | `*` derrière un reverse proxy HTTPS sur le même serveur (Docker : le port 8080 doit alors n'être joignable que par ce proxy, voir `APP_BIND`) |
| `API_DOCS` | `true` : Swagger de l'API parents sur `/api/docs` ; `false` pour le masquer |
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

## 11. Pas à pas : VPS Hostinger (Docker)

Hypothèses : domaine `ardoise-exemple.com`, VPS Ubuntu 24.04 (2 Go de RAM minimum, 4 Go conseillés), Docker. Un hébergement mutualisé Hostinger ne convient pas (pas de Docker, de worker permanent ni de sous-domaines génériques).

### 11.1 Créer le VPS

1. hPanel → VPS → choisir l'offre, puis le système **Ubuntu 24.04 avec Docker** (ou Ubuntu 24.04 seul : installer Docker ensuite avec `curl -fsSL https://get.docker.com | sh`).
2. Définir le mot de passe root, ajouter votre clé SSH, noter l'adresse IP du VPS (`IP`).
3. Se connecter : `ssh root@IP`, puis créer un utilisateur : `adduser deploy && usermod -aG docker,sudo deploy`, et se reconnecter avec `deploy`.
4. Pare-feu (hPanel → VPS → Sécurité, ou `ufw`) : n'ouvrir que 22, 80 et 443.

### 11.2 DNS et certificat HTTPS (wildcard)

Les écoles vivent sous `*.ardoise-exemple.com` : il faut un enregistrement générique et un certificat qui le couvre.

Option recommandée, gratuite et sans renouvellement : **Cloudflare**.

1. Créer un compte Cloudflare, ajouter le domaine, puis remplacer les serveurs de noms chez le registrar (ou dans hPanel → Domaines → DNS / Serveurs de noms) par ceux indiqués par Cloudflare.
2. Dans le DNS Cloudflare : `A  @  → IP` et `A  *  → IP`, nuage orange (proxy) activé.
3. SSL/TLS → mode **Full (strict)**.
4. SSL/TLS → Origin Server → *Create Certificate* : noms `ardoise-exemple.com` et `*.ardoise-exemple.com`, 15 ans. Enregistrer sur le VPS :
   `/etc/ssl/ardoise/origin.pem` (certificat) et `/etc/ssl/ardoise/origin.key` (clé privée, `chmod 600`).

Autre option sans Cloudflare : `certbot certonly --manual --preferred-challenges dns -d ardoise-exemple.com -d '*.ardoise-exemple.com'` (à renouveler à la main tous les 90 jours, ou avec le plugin DNS de votre fournisseur).

### 11.3 Récupérer le code et configurer

```bash
git clone <depot> ardoise && cd ardoise
cp .env.example .env
nano .env
```

Renseigner au minimum :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ardoise-exemple.com
TENANCY_BASE_DOMAIN=ardoise-exemple.com
TENANCY_CENTRAL_DOMAINS=ardoise-exemple.com,www.ardoise-exemple.com
SANCTUM_STATEFUL_DOMAINS=ardoise-exemple.com,*.ardoise-exemple.com
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=*
APP_BIND=127.0.0.1
DB_CONNECTION=mysql
TENANT_DB_CONNECTION=mysql
DB_PASSWORD=<mot de passe fort>
SUPERADMIN_EMAIL=vous@ardoise-exemple.com
SUPERADMIN_PASSWORD=<mot de passe fort>
```

Compléter avec SMTP, FedaPay, SMS et sauvegardes (section 3). `APP_KEY` est générée à l'étape suivante : la sauvegarder hors du serveur.

### 11.4 Reverse proxy Nginx sur le VPS

Le conteneur `app` n'écoute qu'en local (`APP_BIND=127.0.0.1`, port 8080) ; Nginx sur le VPS termine le HTTPS.

```bash
sudo apt install -y nginx
sudo nano /etc/nginx/sites-available/ardoise
```

```nginx
server {
    listen 80;
    server_name ardoise-exemple.com *.ardoise-exemple.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ardoise-exemple.com *.ardoise-exemple.com;

    ssl_certificate     /etc/ssl/ardoise/origin.pem;
    ssl_certificate_key /etc/ssl/ardoise/origin.key;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/ardoise /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Derrière Cloudflare, ne laisser entrer que ses adresses sur les ports 80/443 si vous le souhaitez (liste sur cloudflare.com/ips).

### 11.5 Démarrer et initialiser

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan storage:link --force
docker compose restart app queue scheduler      # prend en compte APP_KEY
docker compose ps                               # app, queue, scheduler, mysql : « running » / « healthy »
curl -I https://ardoise-exemple.com/up          # 200
```

### 11.6 Première école et premiers tests

1. Ouvrir `https://ardoise-exemple.com`, se connecter avec le superadmin (seul compte accepté sur le domaine central), changer le mot de passe et activer la double authentification.
2. Créer une école (code `palmiers`) : son administrateur reçoit ses identifiants par e-mail (vérifier que le conteneur `queue` tourne) et se connecte sur `https://palmiers.ardoise-exemple.com`.
3. Vérifier : `https://palmiers.ardoise-exemple.com/login` affiche le nom de l'école ; la même connexion sur `https://ardoise-exemple.com/login` est refusée (réservée à la plateforme).
4. Application mobile des parents : base `https://ardoise-exemple.com/api/v1`, documentation interactive sur `https://ardoise-exemple.com/api/docs`.
5. Déclarer les webhooks FedaPay (section 3), tester un paiement sandbox, puis passer en `live`.

### 11.7 Exploitation

- Mise à jour : `git pull && docker compose up -d --build`, puis `docker compose exec app php artisan migrate --force && docker compose exec app php artisan tenants:migrate` (sauvegarde d'abord : `docker compose exec app php artisan backup:run`).
- Sauvegardes : déjà planifiées chaque nuit à 01:30 ; copier régulièrement le dossier de sauvegarde, `.env` et `APP_KEY` hors du VPS, et activer les instantanés Hostinger.
- Journaux : `docker compose logs -f app queue scheduler`.
- Redémarrage du VPS : `restart: unless-stopped` relance les conteneurs ; Docker et Nginx démarrent avec le système.

