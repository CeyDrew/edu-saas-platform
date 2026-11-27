#!/bin/sh
# First-run bootstrap for the API container, so `docker compose up` is the only command
# needed: install dependencies, create .env, migrate and seed an empty database.
set -e

cd /var/www/api
rm -f storage/framework/bootstrapped

if [ ! -f vendor/autoload.php ]; then
    echo "[bootstrap] composer install"
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ ! -f .env ]; then
    echo "[bootstrap] creating .env"
    cp .env.example .env
    php artisan key:generate --force
fi

if [ "${BOOTSTRAP_DATABASE:-true}" = "true" ]; then
    php artisan migrate --force
    if [ "$(php artisan tinker --execute='echo \App\Domain\Tenancy\Models\Tenant::count();' 2>/dev/null | tail -n1)" = "0" ]; then
        echo "[bootstrap] seeding demo data"
        php artisan db:seed --force
    fi
    php artisan storage:link >/dev/null 2>&1 || true
fi

touch storage/framework/bootstrapped

exec "$@"
