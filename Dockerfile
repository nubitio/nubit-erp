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
FROM ghcr.io/carthage-software/mago:latest AS mago

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
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    curl \
    python3 \
    python3-pip \
    libpango1.0-dev \
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
    protobuf \
    opentelemetry \
    bcmath

# Install WeasyPrint
RUN pip3 install --break-system-packages WeasyPrint \
    && rm -rf ~/.cache/pip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

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
COPY package.json yarn.lock ./
COPY assets ./assets
COPY scripts ./scripts

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
COPY vite.config.js ./
COPY tsconfig.json ./
COPY postcss.config.mjs ./

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
COPY --from=mago /mago /usr/local/bin/mago

# Install Xdebug
#RUN install-php-extensions xdebug

# Configure PHP for development
COPY docker/php/php.dev.ini $PHP_INI_DIR/conf.d/99-app.ini

# Copy Caddyfile
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

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
COPY docker/php/php.dev.ini $PHP_INI_DIR/conf.d/99-app.ini

# Copy Caddyfile
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

# Copy application code
COPY --chown=www-data:www-data . /app

# Copy built frontend assets from frontend-builder stage
COPY --from=frontend-builder --chown=www-data:www-data /app/public/build /app/public/build
COPY --from=frontend-builder --chown=www-data:www-data /app/public/themes /app/public/themes

# Install dependencies
RUN composer install --no-interaction --no-scripts

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
COPY docker/php/php.prod.ini $PHP_INI_DIR/conf.d/99-app.ini

# Copy Caddyfile
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

# Copy application code
COPY --chown=www-data:www-data . /app

# Copy built frontend assets from frontend-builder stage
COPY --from=frontend-builder --chown=www-data:www-data /app/public/build /app/public/build
COPY --from=frontend-builder --chown=www-data:www-data /app/public/themes /app/public/themes

# Install dependencies and optimize autoloader
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && composer dump-autoload --no-dev --classmap-authoritative \
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
