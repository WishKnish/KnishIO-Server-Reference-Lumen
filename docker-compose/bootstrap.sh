#!/bin/bash

echo '#################### BEGIN: bootstrap.sh ####################'

# Composer install
echo 'Composer install...'
composer config --global github-oauth.github.com $3
composer install

echo 'Waiting while DB will be initialized...'
while ! mysql -h"knishio-db" -u"$1" -p"$2"  -e ";" ; do
    echo "Try to connect username=$1 password=$2..."
    sleep 5
done


echo 'Starting bootstrap process...'
php artisan migrate


echo '#################### END: bootstrap.sh ####################'
exec /bin/sh -c php-fpm
