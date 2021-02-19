#!/bin/bash
/usr/bin/mysqld_safe &
sleep 5
mysql -u root -e "CREATE DATABASE c1_seguridad"
mysql -u root c1_seguridad < /tmp/c1_seguridad.sql