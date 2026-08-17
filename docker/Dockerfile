FROM php:8.4-cli

# Sistem bağımlılıkları ve PHP eklentileri
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        gnupg \
        libicu-dev \
        libsqlite3-dev \
        libzip-dev \
        sqlite3 \
        unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

# Node.js 20 (Vite / Tailwind derlemesi için)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
