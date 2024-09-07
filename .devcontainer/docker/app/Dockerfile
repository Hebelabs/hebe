ARG PHP_EXTENSIONS="mysqli pgsql pdo_mysql pdo_pgsql"

FROM thecodingmachine/php:8.3-v4-apache-node16

COPY . /var/www/html
WORKDIR /var/www/html

# RUN chmod -R 777 /var/www/html


RUN composer install 
# COPY /workspace /var/www/html
# RUN bash /var/www/html/bootstrap.sh

# RUN composer install
# RUN npm install
# RUN npm run build