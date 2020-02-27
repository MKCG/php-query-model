FROM php:7.4-cli

RUN apt update -y && \
    apt install -y netcat git libzip-dev zip

RUN docker-php-ext-install sockets zip pdo_mysql bcmath
RUN pecl install redis-5.1.1

RUN mkdir -p /home/php-query-model

# Composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer
