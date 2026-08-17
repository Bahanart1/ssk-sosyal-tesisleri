#!/usr/bin/env bash
set -e

cd /var/www/html

if [ ! -f .env ]; then
    echo "[entrypoint] .env oluşturuluyor..."
    cp .env.example .env
    sed -i 's|^APP_URL=.*|APP_URL=http://localhost:8000|' .env
fi

echo "[entrypoint] composer install..."
composer install --no-interaction --prefer-dist --no-progress

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "[entrypoint] APP_KEY üretiliyor..."
    php artisan key:generate --force
fi

FRESH_DB=0
if [ ! -f database/database.sqlite ]; then
    echo "[entrypoint] SQLite veritabanı oluşturuluyor..."
    touch database/database.sqlite
    FRESH_DB=1
fi

echo "[entrypoint] npm install..."
npm install --no-audit --no-fund

# Her açılışta derle: aksi halde kod değişince eski asset'ler servis edilir.
echo "[entrypoint] Vite build..."
npm run build

mkdir -p storage/framework/{cache/data,sessions,testing,views} storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache || true

echo "[entrypoint] migrate..."
php artisan migrate --force

if [ "$FRESH_DB" = "1" ]; then
    echo "[entrypoint] seed (demo veriler)..."
    php artisan db:seed --force
fi

php artisan storage:link --force >/dev/null 2>&1 || true

exec "$@"
