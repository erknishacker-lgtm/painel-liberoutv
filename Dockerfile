FROM php:8.2-apache

RUN a2enmod rewrite headers \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Uploads grandes (cards / fundos / atalhos)
RUN printf '%s\n' \
    'upload_max_filesize=25M' \
    'post_max_size=30M' \
    'memory_limit=256M' \
    'max_execution_time=120' \
    > /usr/local/etc/php/conf.d/liberou-uploads.ini

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p data assets/cards \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data /var/www/html/assets/cards \
    && chmod -R 755 /var/www/html

# Persistência no EasyPanel: monte volumes em /var/www/html/data e /var/www/html/assets/cards

EXPOSE 80
