# Dockerfile for Marriot application
# Using multi-stage builds for development, staging, and production environments

# -----------------------------------------------------------------------------
# Build arguments for customization
# -----------------------------------------------------------------------------
ARG PHP_VERSION=8.3

# -----------------------------------------------------------------------------
# Base image
# -----------------------------------------------------------------------------
FROM ubuntu:22.04 AS base

# Set environment variables
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Add metadata labels
LABEL maintainer="Nubit Team"
LABEL org.opencontainers.image.title="Efact Application"
LABEL org.opencontainers.image.description="Efact Application"
LABEL org.opencontainers.image.version="1.0"
LABEL org.opencontainers.image.vendor="Nubit"

# Setup PHP PPA
RUN apt-get update && apt-get install -y --no-install-recommends \
    software-properties-common \
    gnupg \
    ca-certificates \
    && add-apt-repository ppa:ondrej/php -y \
    && rm -rf /var/lib/apt/lists/*

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    curl \
    mcrypt \
    libaio1 \
    build-essential \
    apache2 \
    supervisor \
    python3 \
    python3-pip \
    libpango1.0-dev \
    libapache2-mod-php8.3 \
    php8.3 \
    php8.3-fpm \
    php8.3-redis \
    php8.3-curl \
    php8.3-gd \
    php8.3-intl \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-zip \
    php8.3-mysql \
    php8.3-soap \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Set php 8.3 as the default
RUN update-alternatives --set php /usr/bin/php8.3

# Install WeasyPrint
RUN pip3 install WeasyPrint \
    && rm -rf ~/.cache/pip

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache
RUN a2enmod rewrite ssl
RUN mkdir -p /etc/ssl/private && chmod 755 /etc/ssl/private && chown www-data:www-data /etc/ssl/private
COPY docker/apache/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/servername.conf /etc/apache2/conf-available/servername.conf
RUN a2enconf servername
COPY docker/apache/cert.pem /etc/ssl/certs/cert.pem
COPY docker/apache/cert-key.pem /etc/ssl/private/cert-key.pem
RUN chmod 600 /etc/ssl/private/cert-key.pem && \
    chown www-data:www-data /etc/ssl/certs/cert.pem /etc/ssl/private/cert-key.pem && \
    chown -R www-data:www-data /etc/ssl/private

# Configure Supervisord
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create required log directories for supervisor and apache
RUN mkdir -p /var/log/messenger /var/log/supervisor /var/log/apache2 /var/run/apache2 \
    && chown -R www-data:www-data /var/log/messenger /var/log/supervisor /var/log/apache2 /var/run/apache2 \
    && chmod -R 755 /var/log/messenger /var/log/supervisor /var/log/apache2 /var/run/apache2

# Set working directory
WORKDIR /var/www/html

# Expose ports
EXPOSE 80 443

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

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
    php8.3-xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Configure PHP for development
COPY docker/php/php.dev.ini $PHP_INI_DIR/php.ini

# Mount application code
VOLUME /var/www/html

# Start services with Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# -----------------------------------------------------------------------------
# Staging image
# -----------------------------------------------------------------------------
FROM base AS stg

# Set environment (corrected from 'dev' to 'staging')
ENV APP_ENV=staging

# Configure PHP for staging (using dev config as fallback, ideally should have its own php.stg.ini)
COPY docker/php/php.dev.ini $PHP_INI_DIR/php.ini

# Copy application code
COPY --chown=www-data:www-data . /var/www/html

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
COPY docker/php/php.prod.ini $PHP_INI_DIR/php.ini

# Copy application code
COPY --chown=www-data:www-data . /var/www/html

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
