FROM ubuntu:20.04

MAINTAINER Johan Guerreros (johangm90@gmail.com)

# set noninteractive
ARG DEBIAN_FRONTEND=noninteractive

# update package list
RUN apt-get update -y
RUN apt update

# install prerequisites
RUN apt install -y software-properties-common

# install curl and git
RUN apt install -y curl git zip unzip wget

# install apache
RUN apt install -y apache2

# php ppa
RUN add-apt-repository ppa:ondrej/php -y
RUN apt update

# install php
RUN apt -y install php7.4 mcrypt php7.4-mcrypt php7.4-mbstring php7.4-xml php7.4-mysql php7.4-curl php7.4-zip php7.4-soap php7.4-gd php7.4-intl
RUN apt install -y libapache2-mod-php7.4

# install wkhtmltopdf
SHELL ["/bin/bash", "-o", "pipefail", "-c"]
RUN arch=$(arch | sed s/aarch64/arm64/ | sed s/x86_64/amd64/) && \
    wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6-1/wkhtmltox_0.12.6-1.focal_${arch}.deb && \
    apt install -y ./wkhtmltox_0.12.6-1.focal_${arch}.deb

# Apache conf
# allow .htaccess with RewriteEngine
RUN a2enmod rewrite
# to see live logs we do : docker logs -f [CONTAINER ID]
# without the following line we get "AH00558: apache2: Could not reliably determine the server's fully qualified domain name"
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
# autorise .htaccess files
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
