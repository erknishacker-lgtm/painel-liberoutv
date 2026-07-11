FROM php:8.2-apache

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p data assets/cards \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html

EXPOSE 80
