FROM ubuntu:22.04

MAINTAINER Johan Guerreros (johangm90@gmail.com)

# set noninteractive
ARG DEBIAN_FRONTEND=noninteractive

# update package list and install prerequisites
RUN apt-get update -y && \
    apt-get install -y software-properties-common curl git zip unzip wget apache2 && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# add PHP repository
RUN add-apt-repository ppa:ondrej/php -y && \
    apt-get update

# install PHP and extensions
RUN apt-get install -y php8.2 mcrypt php8.2-xdebug php8.2-mbstring php8.2-xml php8.2-mysql php8.2-curl php8.2-zip php8.2-soap php8.2-gd php8.2-intl php8.2-amqp && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# install wkhtmltopdf
#SHELL ["/bin/bash", "-o", "pipefail", "-c"]
#RUN arch=$(arch | sed s/aarch64/arm64/ | sed s/x86_64/amd64/) && \
#    wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-2/wkhtmltox_0.12.6.1-2.jammy_${arch}.deb && \
#    apt install -y ./wkhtmltox_0.12.6.1-2.jammy_${arch}.deb && \
#    rm wkhtmltox_0.12.6.1-2.jammy_${arch}.deb

# Apache conf
RUN a2enmod rewrite && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add volumes
VOLUME ["/var/www/html"]

EXPOSE 80

WORKDIR /var/www/html/

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
