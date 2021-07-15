#!/bin/bash

set -e

echo '#################### BEGIN: bootstrap.sh ####################'


# echo 'Waiting while DB will be initialized...'

# while ! mysql --protocol TCP -u"$1" -p"$2" -e "show databases;" > /dev/null 2>&1; do
#    sleep 1
# done


# while ! mysql -h"knishio-db" -e ";" ; do
#    sleep 3
# done

while ! mysql -h"knishio-db" -u"root" -p"root"  -e ";" ; do
    echo "Try to connect username=$1 password=$2..."
    sleep 5
done

# wait for mysql
# while ! mysqladmin ping -h"127.0.0.1" -u"$1" -p"$2" --silent; do
# while ! mysqladmin ping -h "knishio-db" --silent; do
#  sleep 1
# done



# echo 'Starting bootstrap process...'


# docker exec knishio-app php artisan migrate

# cd /var/www

# env DB_HOST="knishio-db" php artisan migrate
php artisan migrate

echo '#################### END: bootstrap.sh ####################'

exec "$@"
