# Run: docker-compose up --build -d
# Stop: docker-compose down
# Log: docker logs -f [container name] 1>/dev/null

FROM php:7.4-fpm

# Arguments defined in docker-compose.yml
ARG user
ARG uid
ARG db_password

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    mariadb-client

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


# Copy all files to the working dir & set current owner user for it
COPY --chown=$user:$user . /var/www
RUN chown -R $user:$user /var/www/storage
RUN chmod -R ug+w /var/www/storage

# Execute a bootstrap bash script
# COPY ./docker-compose/bootstrap.sh /usr/bin/bootstrap.sh
# ENTRYPOINT ["/bin/bash", "./docker-compose/bootstrap.sh"]

# CMD /usr/bin/bootstrap.sh root $db_password
# RUN /usr/bin/bootstrap.sh root $db_password
# COPY ./docker-compose/bootstrap.sh /usr/bin/bootstrap.sh
# ENTRYPOINT ["/usr/bin/bootstrap.sh"]

