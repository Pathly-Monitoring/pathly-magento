# Magento Open Source for the integration test of the module.
#
# No official image exists: sources of Adobe (github.com/magento/magento2,
# fixed tag), dependencies from Packagist with Composer, no repo.magento.com
# key. PHP and Apache: official php image. The install itself (database,
# OpenSearch, admin account) is done by dev/integration/run.sh.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

ARG MAGENTO_VERSION=2.4.8-p3

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libxslt1-dev libzip-dev libpng-dev libjpeg62-turbo-dev \
    libfreetype6-dev libonig-dev ca-certificates \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" bcmath gd intl pdo_mysql soap sockets xsl zip ftp opcache \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN printf 'memory_limit=2G\nmax_execution_time=300\nopcache.memory_consumption=256\n' \
    > /usr/local/etc/php/conf.d/zz-magento.ini \
  && a2enmod rewrite headers expires \
  && sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/pub!' \
    /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
RUN git clone --depth 1 --branch "${MAGENTO_VERSION}" https://github.com/magento/magento2.git . \
  && COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --no-interaction --prefer-dist --no-progress \
  && chown -R www-data:www-data /var/www/html

# Apache without root: it listens on 8080, a port www-data can open.
RUN sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
  && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf
EXPOSE 8080
USER www-data
