FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p /var/www/html/data /var/www/html/assets/cards \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data /var/www/html/assets/cards

EXPOSE 80
