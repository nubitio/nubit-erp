# syntax=docker/dockerfile:1
# Dockerfile for Efact application
# Using multi-stage builds with FrankenPHP for development, staging, and production environments

# -----------------------------------------------------------------------------
# Build arguments for customization
# -----------------------------------------------------------------------------
ARG PHP_VERSION=8.5
ARG FRANKENPHP_VERSION=1.10

# -----------------------------------------------------------------------------
# Mago PHP toolchain
# -----------------------------------------------------------------------------
# Pinned to match the schema version declared in mago.toml; :latest (>=1.42)
# enforces stricter exit codes that fail on the current baseline.
FROM ghcr.io/carthage-software/mago:1.40.0 AS mago

# -----------------------------------------------------------------------------
# Base image with FrankenPHP
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION} AS base

# Set environment variables
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV SERVER_NAME=:80

# Add metadata labels
LABEL maintainer="Nubit Team"
LABEL org.opencontainers.image.title="Efact Application"
LABEL org.opencontainers.image.description="Efact Application"
LABEL org.opencontainers.image.version="1.0"
LABEL org.opencontainers.image.vendor="Nubit"

# Install system dependencies
# libpango/cairo/gdk-pixbuf are runtime-only (no -dev headers) — WeasyPrint
# links against the shared libs, it doesn't compile anything against them.
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    curl \
    python3 \
    python3-pip \
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libgdk-pixbuf-2.0-0 \
    libcairo2 \
    mariadb-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install PHP extensions
RUN install-php-extensions \
    pdo_mysql \
    redis \
    gd \
    intl \
    zip \
    soap \
    imagick \
    opcache \
    bcmath

# Install WeasyPrint
RUN pip3 install --break-system-packages WeasyPrint \
    && rm -rf ~/.cache/pip

# Install Composer
COPY --link --from=composer:latest /usr/bin/composer /usr/bin/composer

# Entrypoint
COPY --link docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Caddy's TLS storage / admin autosave dirs — owned by www-data so they stay
# writable once stg/prod drop root privileges (see docker/entrypoint.sh).
RUN chown -R www-data:www-data /data /config

# Set working directory
WORKDIR /app

# Expose ports
EXPOSE 80 443

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=30s --retries=3 \
    CMD sh -lc 'status=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/health || true); [ "$status" = "200" ] || [ "$status" = "503" ]'

# Common entrypoint (used by all stages)
ENTRYPOINT ["/entrypoint.sh"]

# -----------------------------------------------------------------------------
# Frontend builder stage
# -----------------------------------------------------------------------------
FROM node:20-alpine AS frontend-builder

WORKDIR /app

# Copy package files and assets (needed for postinstall script)
COPY --link package.json yarn.lock ./
COPY --link assets ./assets
COPY --link scripts ./scripts

# Create public/themes directory for theme compilation
RUN mkdir -p public/themes

# Install dependencies (Yarn 4 with Corepack)
# Configure to use node_modules instead of PnP for compatibility with sass
# Use --mode skip-build to skip lifecycle scripts (postinstall, etc)
RUN corepack enable && \
    yarn set version stable && \
    yarn config set nodeLinker node-modules && \
    yarn install --mode skip-build

# Run theme build after dependencies are installed
RUN yarn build:themes

# Copy remaining source files needed for build
COPY --link vite.config.js ./
COPY --link tsconfig.json ./
COPY --link postcss.config.mjs ./

# Build frontend assets
RUN yarn build

# -----------------------------------------------------------------------------
# Development image
# -----------------------------------------------------------------------------
FROM base AS dev

# Set environment
ENV APP_ENV=dev

# Install development tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    vim \
    lnav \
    tmux \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Mago
COPY --from=mago /usr/local/bin/mago /usr/local/bin/mago

# Install Xdebug
#RUN install-php-extensions xdebug

# Configure PHP for development
COPY --link docker/php/php.dev.ini $PHP_INI_DIR/conf.d/99-app.ini

# Copy Caddyfile
COPY --link docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

# Mount application code
VOLUME /app

# Start FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# -----------------------------------------------------------------------------
# Staging image
# -----------------------------------------------------------------------------
FROM base AS stg

# Set environment
ENV APP_ENV=staging

# Configure PHP for staging
COPY --link docker/php/php.dev.ini $PHP_INI_DIR/conf.d/99-app.ini

# Copy Caddyfile
COPY --link docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

# Copy dependency manifests first so `composer install` is cached across
# source-only changes, then install without the autoloader/scripts (both
# need the full app source, which isn't copied in yet).
COPY --link composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY --link --chown=33:33 . /app

# Copy built frontend assets from frontend-builder stage
COPY --link --from=frontend-builder --chown=33:33 /app/public/build /app/public/build
COPY --link --from=frontend-builder --chown=33:33 /app/public/themes /app/public/themes

RUN composer dump-autoload

# Set permissions and create required directories
RUN mkdir -p var/cache/dev var/log \
    && chown -R www-data:www-data var public \
    && chmod -R 775 var public \
    && chmod +x bin/console

# Start FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# -----------------------------------------------------------------------------
# Production image
# -----------------------------------------------------------------------------
FROM base AS prod

# Set environment
ENV APP_ENV=prod

# Configure PHP for production
COPY --link docker/php/php.prod.ini $PHP_INI_DIR/conf.d/99-app.ini

# Copy Caddyfile
COPY --link docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

# Copy dependency manifests first so `composer install` is cached across
# source-only changes, then install without the autoloader/scripts (both
# need the full app source, which isn't copied in yet).
COPY --link composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY --link --chown=33:33 . /app

# Copy built frontend assets from frontend-builder stage
COPY --link --from=frontend-builder --chown=33:33 /app/public/build /app/public/build
COPY --link --from=frontend-builder --chown=33:33 /app/public/themes /app/public/themes

# Generate optimized autoloader now that the full source is present
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer dump-env prod \
    && rm -rf /root/.composer/cache

# Set permissions and create required directories
RUN mkdir -p var/cache/prod var/log \
    && chown -R www-data:www-data var public \
    && chmod -R 775 var public \
    && chmod +x bin/console \
    && php bin/console cache:clear \
    && php bin/console assets:install

# Start FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
