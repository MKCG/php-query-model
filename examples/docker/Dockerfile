FROM php:7.4-cli

RUN apt update -y && \
    apt install -y netcat git libzip-dev zip  libcurl4-openssl-dev pkg-config libssl-dev

RUN docker-php-ext-install sockets zip pdo_mysql bcmath opcache
RUN pecl install redis-5.1.1
RUN pecl install mongodb
RUN echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini

RUN mkdir -p /home/php-query-model

# Composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer
