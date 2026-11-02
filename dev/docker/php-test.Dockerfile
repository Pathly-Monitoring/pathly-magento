# PHP en ligne de commande pour les tests unitaires du module, avec Xdebug
# (couverture des branches) et Composer. Version de PHP en argument.
#   docker build --build-arg PHP_VERSION=8.1 -t pathly-magento-test:8.1 -f dev/docker/php-test.Dockerfile dev/docker
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli
RUN apt-get update \
  && apt-get install -y --no-install-recommends git unzip zip libicu-dev libzip-dev $PHPIZE_DEPS \
  && docker-php-ext-install -j"$(nproc)" intl bcmath zip \
  && pecl install xdebug-3.3.2 \
  && docker-php-ext-enable xdebug \
  && apt-get purge -y --auto-remove $PHPIZE_DEPS \
  && rm -rf /var/lib/apt/lists/* /tmp/pear
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV XDEBUG_MODE=coverage COMPOSER_MEMORY_LIMIT=-1
RUN printf 'memory_limit=2G\n' > /usr/local/etc/php/conf.d/zz-test.ini
WORKDIR /module
