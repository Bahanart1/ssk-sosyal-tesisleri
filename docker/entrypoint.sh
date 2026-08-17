#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# Konteyner ilk açılışta projeyi kullanılabilir hale getirir.
# Her adım "zaten yapılmışsa atla" mantığıyla çalışır; tekrar tekrar
# çalıştırmak güvenlidir ve mevcut veriyi silmez.
# =============================================================================

log() { printf '\033[0;36m▸ %s\033[0m\n' "$1"; }

cd /var/www/html

# --- .env ---
if [ ! -f .env ]; then
    log ".env bulunamadı, .env.example kopyalanıyor"
    cp .env.example .env
fi

# --- Composer bağımlılıkları ---
if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    log "Composer bağımlılıkları kuruluyor (ilk açılışta birkaç dakika sürebilir)"
    composer install --no-interaction --prefer-dist --no-progress
fi

# --- Uygulama anahtarı ---
if ! grep -qE '^APP_KEY=base64:' .env; then
    log "Uygulama anahtarı üretiliyor"
    php artisan key:generate --force
fi

# --- SQLite veritabanı ---
DB_FILE="database/database.sqlite"
FRESH_DB=false
if [ ! -f "$DB_FILE" ]; then
    log "SQLite veritabanı oluşturuluyor"
    mkdir -p database
    touch "$DB_FILE"
    FRESH_DB=true
fi

# --- Şema ---
log "Migration'lar çalıştırılıyor"
php artisan migrate --force --no-interaction

# Veritabanı yeni oluşturulduysa gerçek 2026 verisiyle doldur
if [ "$FRESH_DB" = true ]; then
    log "Başlangıç verisi yükleniyor (tesisler, devreler, tarifeler, demo hesaplar)"
    php artisan db:seed --force --no-interaction

    if [ "${SEED_DEMO_RESERVATIONS:-false}" = "true" ]; then
        log "Demo başvurular üretiliyor"
        php artisan db:seed --class=DemoReservationSeeder --force --no-interaction
    fi
fi

# --- Frontend varlıkları ---
if [ ! -d public/build ]; then
    log "Frontend varlıkları derleniyor"
    npm ci --no-audit --no-fund
    npm run build
fi

# --- Depolama ---
mkdir -p storage/app/private storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rw storage bootstrap/cache || true

if [ ! -e public/storage ]; then
    php artisan storage:link || true
fi

# Önbellekleri temizle (kod bind-mount edildiği için bayat derleme kalmasın)
php artisan optimize:clear >/dev/null 2>&1 || true

log "Hazır → http://localhost:8000"
log "Yönetici: admin@sigortader.com.tr / admin123"
log "Üye:      TC 12345678901 / musteri123"

exec "$@"
