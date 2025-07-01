# Use PHP 8.3 with Apache as the base image
FROM php:8.3-apache AS base

# Set metadata
LABEL maintainer="Johan Guerreros <johangm90@gmail.com>"
LABEL description="PHP 8.3 Apache image for efact application"
LABEL version="1.0"

# Set environment variables
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV PHP_MEMORY_LIMIT=256M
ENV PHP_MAX_EXECUTION_TIME=30
ENV PHP_UPLOAD_MAX_FILESIZE=20M
ENV PHP_POST_MAX_SIZE=20M

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    zip \
    unzip \
    wget \
    libicu-dev \
    libzip-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libgmp-dev \
    libpq-dev \
    libxslt1-dev \
    libonig-dev \
    libsodium-dev \
    libmagickwand-dev \
    # WeasyPrint dependencies
    libpango1.0-dev \
    python3-pip \
    python3-dev \
    python3-setuptools \
    python3-wheel \
    python3-venv \
    # Redis
    libhiredis-dev \
    # Cleanup
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    intl \
    zip \
    soap \
    gd \
    opcache \
    pdo_mysql \
    exif \
    bcmath \
    sockets \
    && pecl install redis imagick xdebug \
    && docker-php-ext-enable redis imagick

# Install WeasyPrint
RUN python3 -m venv /opt/venv && \
    /opt/venv/bin/pip install --no-cache-dir WeasyPrint && \
    ln -sf /opt/venv/bin/weasyprint /usr/local/bin/weasyprint

# Configure Apache
RUN a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Configure PHP
RUN { \
    echo 'memory_limit=${PHP_MEMORY_LIMIT}'; \
    echo 'max_execution_time=${PHP_MAX_EXECUTION_TIME}'; \
    echo 'upload_max_filesize=${PHP_UPLOAD_MAX_FILESIZE}'; \
    echo 'post_max_size=${PHP_POST_MAX_SIZE}'; \
    echo 'error_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT'; \
    echo 'display_errors=Off'; \
    echo 'display_startup_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'error_log=/dev/stderr'; \
    echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/custom.ini

# Configure Xdebug (only for development)
RUN { \
    echo 'xdebug.mode=debug,coverage'; \
    echo 'xdebug.client_host=host.docker.internal'; \
    echo 'xdebug.client_port=9003'; \
    echo 'xdebug.start_with_request=trigger'; \
    } > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure permissions
ARG USER_ID=1000
ARG GROUP_ID=1000

RUN usermod -u ${USER_ID} www-data \
    && groupmod -g ${GROUP_ID} www-data \
    && chown -R www-data:www-data /var/www

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Set working directory
WORKDIR /var/www/html/

# Expose port
EXPOSE 80

# Set volume
VOLUME ["/var/www/html"]

# Start Apache
CMD ["apache2-foreground"]
