FROM ubuntu:22.04

MAINTAINER Johan Guerreros (johangm90@gmail.com)

# set noninteractive
ARG DEBIAN_FRONTEND=noninteractive

# update package list
RUN apt-get update

# install prerequisites
RUN apt-get install -y software-properties-common

# install curl and git
RUN apt-get install -y curl git zip unzip wget

# install apache
RUN apt-get install -y apache2

# php ppa
RUN add-apt-repository ppa:ondrej/php -y
RUN apt-get update

# install php
RUN apt-get -y install php8.3 mcrypt php8.3-xdebug php8.3-mbstring php8.3-xml php8.3-mysql php8.3-curl php8.3-zip php8.3-soap php8.3-gd php8.3-intl php8.3-redis php8.3-imagick
RUN apt-get install -y libapache2-mod-php8.3

RUN update-alternatives --set php /usr/bin/php8.3

# configure xdebug
RUN sed -i -e '$a xdebug.mode=debug,coverage' /etc/php/8.3/mods-available/xdebug.ini
RUN sed -i -e '$a xdebug.client_host=host.docker.internal' /etc/php/8.3/mods-available/xdebug.ini
RUN sed -i -e '$a xdebug.client_port=9003' /etc/php/8.3/mods-available/xdebug.ini

# install weasyprint
RUN apt-get update
RUN apt-get -y install libpango1.0-dev python3-pip
RUN pip3 install WeasyPrint

# Apache conf
# allow .htaccess with RewriteEngine
RUN a2enmod rewrite
# to see live logs we do : docker logs -f [CONTAINER ID]
# without the following line we get "AH00558: apache2: Could not reliably determine the server's fully qualified domain name"
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
# allow .htaccess files
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
# set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN mv composer.phar /usr/local/bin/composer

# Configure permissions
ARG USER_ID=1000
ARG GROUP_ID=1000

RUN userdel -f www-data &&\
    if getent group www-data ; then groupdel www-data; fi &&\
    groupadd -g ${GROUP_ID} www-data &&\
    useradd -l -u ${USER_ID} -g www-data www-data &&\
    install -d -m 0755 -o www-data -g www-data /home/www-data &&\
    chown --changes --silent --no-dereference --recursive \
          --from=33:33 ${USER_ID}:${GROUP_ID} \
        /home/www-data

# Add volumes
VOLUME ["/var/www/html"]

EXPOSE 80

WORKDIR /var/www/html/

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
