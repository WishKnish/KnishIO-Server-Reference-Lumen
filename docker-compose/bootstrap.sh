#!/bin/bash

echo 'Waiting while DB will be initialized...'

# while ! mysql --protocol TCP -u"$1" -p"$2" -e "show databases;" > /dev/null 2>&1; do
#    sleep 1
# done

# while ! mysql -h"knishio-db" -u"root" -p"root"  -e ";" ; do
#    sleep 5
# done

# wait for mysql
# while ! mysqladmin ping -h"127.0.0.1" -u"$1" -p"$2" --silent; do
# while ! mysqladmin ping -h "knishio-db" --silent; do
#  sleep 1
# done


echo 'Starting bootstrap process...'

# env DB_HOST="knishio-db" php artisan migrate
php artisan migrate
