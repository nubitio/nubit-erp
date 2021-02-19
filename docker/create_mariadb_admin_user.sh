#!/bin/bash

/usr/bin/mysqld_safe > /dev/null 2>&1 &

RET=1
while [[ RET -ne 0 ]]; do
    echo "=> Waiting for confirmation of MySQL service startup"
    sleep 5
    mysql -uroot -e "status" > /dev/null 2>&1
    RET=$?
done

#PASS=${MYSQL_PASS:-$(pwgen -s 12 1)}
#_word=$( [ ${MYSQL_PASS} ] && echo "preset" || echo "random" )
#echo "=> Creating MySQL admin user with ${_word} password"

mysql -uroot -e "CREATE USER 'c1_seguridad'@'%' IDENTIFIED BY 'Master.2016'"
mysql -uroot -e "GRANT ALL PRIVILEGES ON *.* TO 'c1_seguridad'@'%' WITH GRANT OPTION"

# You can create a /init_db.sh file to intialized the DB
if [ -f /init_db.sh ] ; then
  . /init_db.sh
fi

echo "=> Done!"

echo "========================================================================"
echo "You can now connect to this MySQL Server using:"
echo ""
echo "    mysql -uc1_seguridad -pMaster.2016 -h<host> -P<port>"
echo ""
echo "Please remember to change the above password as soon as possible!"
echo "MySQL user 'root' has no password but only allows local connections"
echo "========================================================================"

mysqladmin -uroot shutdown
