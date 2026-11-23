#!/bin/sh
# Point d'entrée de l'image Ardoise.
# - Vérifie la configuration minimale.
# - Si RUN_MIGRATIONS=true (à activer sur UN SEUL service, ex. le service web Render),
#   applique les migrations de la base centrale et des écoles et crée le lien storage.
# - Puis exécute la commande demandée (supervisord par défaut ; queue:work / schedule:work
#   avec docker compose).
set -e

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "ERREUR : APP_KEY est vide. Générez-la (php artisan key:generate --show) et définissez-la en variable d'environnement." >&2
    exit 1
fi

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache database/tenants

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "==> Migrations de la base centrale"
    php artisan migrate --force

    echo "==> Initialisation des données centrales"
    php artisan db:seed --force

    echo "==> Migrations des bases des établissements"
    php artisan tenants:migrate

    echo "==> Création du lien de stockage"
    php artisan storage:link || true
fi


exec "$@"
