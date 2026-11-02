#!/bin/sh
# Tests unitaires en Docker, sur les sources de Magento d'un volume.
# Usage : dev/unit.sh <php 8.1|8.3> <version de Magento> [--coverage] [arguments de PHPUnit]
# Exemple : dev/unit.sh 8.3 2.4.8-p3 --coverage
set -eu
php="$1"; magento="$2"; shift 2
cd "$(dirname "$0")/.."
docker volume create "pathly-magento-src-$magento" >/dev/null
docker volume create pathly-composer-cache >/dev/null
run() {
  MSYS_NO_PATHCONV=1 docker run --rm -e MAGENTO_ROOT=/magento \
    -v "$(pwd -W 2>/dev/null || pwd):/module" -v "pathly-magento-src-$magento:/magento" \
    -v pathly-composer-cache:/root/.composer/cache -w /module "pathly-magento-test:$php" "$@"
}
run sh dev/magento-sources.sh "$magento" /magento >/dev/null
if [ "${1:-}" = "--coverage" ]; then
  shift
  run sh -c "php -d memory_limit=2G dev/tools/phpunit/vendor/bin/phpunit --path-coverage --coverage-cobertura build/cobertura.xml --coverage-text=build/coverage.txt $* && php dev/coverage-check.php build/cobertura.xml"
else
  run php -d memory_limit=2G -d xdebug.mode=off dev/tools/phpunit/vendor/bin/phpunit "$@"
fi
