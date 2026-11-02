# ReserveBot web app image: Laravel served by Apache.
# Built automatically by `docker compose up --build`.
FROM php:8.5-apache

# - unzip: Composer needs it to unpack packages
# - pdo_mysql: lets PHP talk to MariaDB (the base image doesn't include it)
# - gd + exif: shrink profile photos and turn sideways phone photos upright (the lib*-dev packages are what gd is built with)
# - zip: read and make the timetable's Excel (.xlsx) files (built with libzip-dev)
# - rewrite: lets addresses like /login reach Laravel (public/.htaccess)
RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install pdo_mysql gd exif zip \
 && a2enmod rewrite

# Photos up to 5 MB (the site's limit), with room to spare; shrinking a big photo needs memory
RUN printf 'upload_max_filesize=8M\npost_max_size=10M\nmemory_limit=512M\n' > /usr/local/etc/php/conf.d/reservebot.ini

# Apache serves Laravel's public/ folder, not the whole project
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Install PHP packages first, so this slow step is reused until composer.json or composer.lock change
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# Then the app's own code (.dockerignore keeps .env, vendor and other local files out)
COPY . .
RUN composer dump-autoload --optimize --no-dev \
 && mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/private/photos bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# Builds or updates the database on every start, then starts Apache
COPY docker/entrypoint.sh /usr/local/bin/reservebot-start
RUN sed -i 's/\r$//' /usr/local/bin/reservebot-start && chmod +x /usr/local/bin/reservebot-start

EXPOSE 80
ENTRYPOINT ["reservebot-start"]
CMD ["apache2-foreground"]
