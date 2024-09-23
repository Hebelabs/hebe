FROM php:8.3-fpm-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN set -eux; \
    install-php-extensions pdo pdo_mysql mysqli;

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2.4 /usr/bin/composer /usr/bin/composer

COPY ./app .

RUN composer install --prefer-dist --no-dev --no-scripts --no-progress --no-interaction


COPY ./app/system/data/db_config.php.example ./var/www/html/system/data/db_config.php





RUN php sura -copy /var/www/html/system/data/config.php.example /var/www/html/system/data/config.php
RUN php sura -copy /var/www/html/system/data/db_config.php.example /var/www/html/system/data/db_config.php
# RUN php sura -migrate
# RUN php sura -make:add-user Ivan Ivanov ivanov@example.ru example


RUN composer dump-autoload --optimize

# COPY ./php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# RUN set -eux; \
    # install-php-extensions xdebug