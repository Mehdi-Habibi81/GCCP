# Updated Dockerfile: run nginx + php-fpm in one container and listen on $PORT

# --- builder: install dependencies with Composer ---
FROM php:8.1-fpm AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mbstring

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

# --- runtime: php-fpm + nginx ---
FROM php:8.1-fpm

# Install nginx and gettext (for envsubst) and runtime deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    gettext-base \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mbstring

WORKDIR /var/www/html

# Copy vendor and application files
COPY --from=builder /app/vendor ./vendor
COPY . .

# Copy nginx template (will be processed at container start)
COPY docker/nginx.conf.template /etc/nginx/conf.d/default.conf.template

# Ensure writable directories exist
RUN mkdir -p public/qr /run/php \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 0755 /var/www/html

EXPOSE 80

# Start php-fpm (daemonized) and nginx (foreground). Use PORT env var (default 80).
CMD ["sh", "-c", "export PORT=${PORT:-80} && envsubst '$$PORT' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf && php-fpm -D && nginx -g 'daemon off;'" ]
