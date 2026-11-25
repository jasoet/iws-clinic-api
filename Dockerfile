# The clinic API in a container. Build: docker build -t iws-clinic-api .
# Add SQL Server drivers with: docker build --build-arg WITH_SQLSRV=1 -t iws-clinic-api .
#
# This runs PHP's built-in server, which is fine behind a reverse proxy that
# terminates HTTPS for one caller (iws). For more load, run it under php-fpm
# + nginx instead (README.md, bagian "7. Go-live").

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM php:8.4-cli
ARG WITH_SQLSRV=0
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql pdo_mysql \
 && if [ "$WITH_SQLSRV" = "1" ]; then \
      apt-get install -y --no-install-recommends unixodbc-dev $PHPIZE_DEPS \
      && pecl install sqlsrv pdo_sqlsrv && docker-php-ext-enable sqlsrv pdo_sqlsrv; \
    fi \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY --from=vendor /app /app
RUN mkdir -p storage/framework/cache storage/framework/views storage/framework/sessions storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && php artisan package:discover --ansi

USER www-data
ENV LOG_CHANNEL=stderr APP_ENV=production APP_DEBUG=false SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync
EXPOSE 8080
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
