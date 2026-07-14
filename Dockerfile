FROM php:8.2-apache

# 1) libs do sistema  2) extensões PHP  3) módulos Apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        pkg-config \
    && docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Uploads grandes (cards / fundos / APK Liberou ~37–50MB)
# 36M era o limite antigo e estourava com o APK real (~38.7MB).
# display_errors=Off evita warning "headers already sent" quando POST estoura.
RUN printf '%s\n' \
    'upload_max_filesize=200M' \
    'post_max_size=220M' \
    'memory_limit=512M' \
    'max_execution_time=600' \
    'max_input_time=600' \
    'display_errors=Off' \
    'log_errors=On' \
    'error_reporting=E_ALL' \
    > /usr/local/etc/php/conf.d/liberou-uploads.ini \
    && printf '%s\n' \
    'LimitRequestBody 230686720' \
    > /etc/apache2/conf-available/liberou-upload.conf \
    && a2enconf liberou-upload

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
