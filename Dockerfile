FROM phusion/baseimage

MAINTAINER Johan Guerreros <johangm90@gmail.com>

# Avoid ERROR: invoke-rc.d: policy-rc.d denied execution of start.
RUN echo "#!/bin/sh\nexit 0" > /usr/sbin/policy-rc.d

# Install packages
ENV DEBIAN_FRONTEND noninteractive
RUN apt update
RUN apt install supervisor git curl -y
RUN apt install apache2 apache2-utils libapache2-mod-php -y
RUN apt install php php-cli php-gd php-mbstring php-mcrypt php-memcache php-mysql php-curl php-xml php-zip php-opcache php-apcu -y
RUN apt install mariadb-server -y
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set servername
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf


# Add image configuration and scripts
ADD /image/create_mariadb_admin_user.sh /create_mariadb_admin_user.sh
ADD /image/start-apache2.sh /start-apache2.sh
ADD /image/start-mysqld.sh /start-mysqld.sh
ADD /image/init_db.sh /init_db.sh
ADD /image/run.sh /run.sh
RUN chmod 755 /*.sh
ADD /image/supervisord-apache2.conf /etc/supervisor/conf.d/supervisord-apache2.conf
ADD /image/supervisord-mysqld.conf /etc/supervisor/conf.d/supervisord-mysqld.conf
ADD /image/c1_seguridad.sql /tmp/c1_seguridad.sql

# Clean apt
RUN apt clean && apt autoclean

# Change bind address to 0.0.0.0
RUN sed -i -r 's/bind-address.*$/bind-address = 0.0.0.0/' /etc/mysql/mariadb.conf.d/50-server.cnf

# Remove pre-installed database
RUN rm -rf /var/lib/mysql/*

# Enable .htaccess
ADD /image/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# Configure app
RUN rm -fr /var/www/html
ADD /dist /var/www/html
RUN cd /var/www/html && composer install
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 /var/www/html

#Environment variables to configure php
ENV PHP_UPLOAD_MAX_FILESIZE 25M
ENV PHP_POST_MAX_SIZE 25M

# Add volumes
VOLUME ["/etc/mysql", "/var/lib/mysql", "/var/www/html"]

# Set TERM environment variable
ENV TERM xterm

EXPOSE 80 3306
CMD ["/run.sh"]
