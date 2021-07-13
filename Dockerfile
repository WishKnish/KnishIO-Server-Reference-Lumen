# Run: docker-compose up --build -d
# Stop: docker-compose down
# Log: docker logs -f [container name] 1>/dev/null

FROM php:7.4-fpm

# Arguments defined in docker-compose.yml
ARG user
ARG uid

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install php-redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www

USER $user


COPY ./docker-compose/bootstrap.sh /usr/bin/bootstrap.sh
# ENTRYPOINT /usr/bin/bootstrap.sh
RUN /usr/bin/bootstrap.sh


# These line for /entrypoint.sh
#COPY ./docker-compose/bootstrap.sh /home/$user/bootstrap.sh
# RUN chmod -R 777 /entrypoint.sh
#RUN chmod +x /home/$user/bootstrap.sh
#RUN /home/$user/bootstrap.sh
