#!/bin/sh
# Sources de Magento Open Source pour les tests unitaires : étiquette figée du
# dépôt d'Adobe (github.com/magento/magento2), dépendances depuis Packagist,
# sans clé repo.magento.com. Usage : dev/magento-sources.sh 2.4.6-p15 /chemin
set -eu
version="$1"
dest="$2"
if [ -f "$dest/vendor/autoload.php" ]; then
  echo "Magento $version déjà prêt dans $dest"
  exit 0
fi
mkdir -p "$dest"
git clone --quiet --depth 1 --branch "$version" https://github.com/magento/magento2.git "$dest"
cd "$dest"
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --no-interaction --no-progress \
  --prefer-dist --ignore-platform-reqs --no-scripts
echo "Magento $version prêt dans $dest"
