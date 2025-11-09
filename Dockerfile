# Dockerfile for Efact application
# Using multi-stage builds with FrankenPHP for development, staging, and production environments

# -----------------------------------------------------------------------------
# Build arguments for customization
# -----------------------------------------------------------------------------
ARG PHP_VERSION=8.3
ARG FRANKENPHP_VERSION=latest

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
    supervisor \
    python3 \
    python3-pip \
    libpango1.0-dev \
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
    opentelemetry

# Install WeasyPrint
RUN pip3 install --break-system-packages WeasyPrint \
    && rm -rf ~/.cache/pip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Supervisord
COPY docker/frankenphp/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create required log directories
RUN mkdir -p /var/log/messenger /var/log/supervisor \
    && chown -R www-data:www-data /var/log/messenger /var/log/supervisor \
    && chmod -R 755 /var/log/messenger /var/log/supervisor

# Set working directory
WORKDIR /app

# Expose ports
EXPOSE 80 443

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# -----------------------------------------------------------------------------
# Frontend builder stage
# -----------------------------------------------------------------------------
FROM node:20-alpine AS frontend-builder

WORKDIR /app

# Copy package files
COPY package.json yarn.lock ./

# Install dependencies (Yarn 4 with Corepack)
RUN corepack enable && \
    yarn set version stable && \
    yarn install

# Copy source files needed for build
COPY assets ./assets
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

# Install Xdebug
RUN install-php-extensions xdebug

# Configure PHP for development
COPY docker/php/php.dev.ini $PHP_INI_DIR/conf.d/99-app.ini

# Copy Caddyfile
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

# Mount application code
VOLUME /app

# Start services with Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

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

# Install dependencies
RUN composer install --no-interaction --no-scripts

# Set permissions and create required directories
RUN mkdir -p var/cache/dev var/log \
    && chown -R www-data:www-data var public \
    && chmod -R 775 var public \
    && chmod +x bin/console

# Start services with Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

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

# Start services with Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
