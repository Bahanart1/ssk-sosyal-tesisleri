# syntax=docker/dockerfile:1

# =============================================================================
# SSK Sosyal Tesisleri — geliştirme/deneme imajı
#
# Uygulama SQLite kullanır; ayrı bir veritabanı servisi gerekmez.
# Kaynak kod compose tarafından bind-mount edilir, bu yüzden imaj yalnızca
# çalışma ortamını (PHP + eklentiler, Composer, Node) sağlar.
# =============================================================================

FROM php:8.4-cli-bookworm

# --- Sistem bağımlılıkları ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libsqlite3-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        ca-certificates \
        curl \
    && rm -rf /var/lib/apt/lists/*

# --- PHP eklentileri ---
# pdo_sqlite: veritabanı · zip: composer · intl, bcmath: Laravel yardımcıları
# gd: test paketi UploadedFile::fake()->image() ile sahte belge üretir
RUN docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_sqlite zip intl bcmath gd

# Yükleme boyutu: kimlik belgesi/dekont yüklemeleri 5 MB'a kadar
RUN { \
        echo "upload_max_filesize=8M"; \
        echo "post_max_size=32M"; \
        echo "memory_limit=512M"; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Node (varlık derlemesi için) ---
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
