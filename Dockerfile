# Dockerfile for GCCP PHP app (Render-compatible)
# Uses multi-stage build to install Composer dependencies and produce a lean runtime image.

# --- builder: install dependencies with Composer ---
FROM php:8.1-fpm AS builder

# Install system deps for ext-pdo_mysql, gd, and common tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mbstring

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

# --- runtime image ---
FROM php:8.1-fpm

# Install runtime extensions required (gd, pdo_mysql, mbstring)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mbstring

# Copy application files
WORKDIR /var/www/html
COPY --from=builder /app/vendor ./vendor
COPY . .

# Ensure writable directories exist
RUN mkdir -p public/qr \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 0755 /var/www/html

# Expose FPM socket port
EXPOSE 9000

# Use the default PHP-FPM entrypoint
CMD ["php-fpm"]
