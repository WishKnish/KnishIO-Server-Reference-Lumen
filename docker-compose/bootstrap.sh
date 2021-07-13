#!/bin/bash

echo 'Starting bootstrap process...'

# cd html
# ls

# cd /var/www
# chmod +x artisan
php artisan migrate
