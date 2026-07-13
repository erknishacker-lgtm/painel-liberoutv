FROM php:8.2-apache

# 1) libs do sistema  2) extensões PHP  3) módulos Apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        pkg-config \
    && docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Uploads grandes (cards / fundos / APK)
RUN printf '%s\n' \
    'upload_max_filesize=32M' \
    'post_max_size=36M' \
    'memory_limit=256M' \
    'max_execution_time=180' \
    > /usr/local/etc/php/conf.d/liberou-uploads.ini

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p data assets/cards assets/apk \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data /var/www/html/assets/cards /var/www/html/assets/apk \
    && chmod -R 755 /var/www/html

# EasyPanel: monte volumes em
#   /var/www/html/data
#   /var/www/html/assets/cards
#   /var/www/html/assets/apk

EXPOSE 80
