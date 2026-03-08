# ─────────────────────────────────────────────────────────────────────────────
# Stage: base — shared PHP 8.3 setup for both targets
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

LABEL maintainer="Ogami ERP <dev@ogamierp.local>"

# System dependencies
RUN apk add --no-cache \
    linux-headers \
    bash \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    postgresql-client \
    icu-dev \
    libxml2-dev \
    libxslt-dev \
    shadow \
    git \
    unzip

# PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        xml \
        xsl \
        soap \
        sockets \
        opcache

# Redis extension via PECL (requires autoconf + g++ for compilation)
RUN apk add --no-cache --virtual .phpize-deps autoconf g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .phpize-deps

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ─────────────────────────────────────────────────────────────────────────────
# Stage: development — artisan serve with volume-mounted source
# ─────────────────────────────────────────────────────────────────────────────
FROM base AS development

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-dev.ini

# Create storage directories first (needed for artisan commands)
RUN mkdir -p /var/www/html/bootstrap/cache \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Install dependencies (will be overridden by volume mount in compose)
COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# ─────────────────────────────────────────────────────────────────────────────
# Stage: frontend-builder — compiles React/Vite SPA to public/build/
# ─────────────────────────────────────────────────────────────────────────────
FROM node:22-alpine AS frontend-builder

WORKDIR /app

# Install pnpm
RUN corepack enable && corepack prepare pnpm@latest --activate

# Install dependencies (leverages Docker layer cache when lockfile unchanged)
COPY frontend/package.json frontend/pnpm-lock.yaml ./frontend/
RUN cd frontend && pnpm install --frozen-lockfile

# Copy full frontend source and run build
COPY frontend/ ./frontend/
# public/ is the output target (outDir: '../public/build')
RUN mkdir -p ./public/build && cd frontend && pnpm build

# ─────────────────────────────────────────────────────────────────────────────
# Stage: production — Nginx + PHP-FPM + Supervisor
# ─────────────────────────────────────────────────────────────────────────────
FROM base AS production

RUN apk add --no-cache nginx supervisor

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-prod.ini
COPY docker/php/fpm-prod.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Install production dependencies
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

# Inject pre-built frontend assets from the frontend-builder stage
COPY --from=frontend-builder /app/public/build ./public/build

RUN mkdir -p /var/www/html/bootstrap/cache \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

RUN composer dump-autoload --optimize --classmap-authoritative \
    && php artisan view:cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

