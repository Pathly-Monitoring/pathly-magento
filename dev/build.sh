#!/bin/sh
# Archive à publier (Adobe Commerce Marketplace, release GitHub) : le paquet
# Composer, composer.json à la racine, sans les tests ni les outils.
#   build/pathly-module-monitoring-<version>.zip  et  build/package/ (dépliée)
# Lancé dans un conteneur PHP avec Composer (dev/unit.sh) ou dans la CI.
set -eu
cd "$(dirname "$0")/.."
version=$(php -r 'echo json_decode(file_get_contents("composer.json"), true)["version"];')
grep -q "MODULE = '$version'" Model/Version.php || { echo "Model/Version.php ne porte pas $version"; exit 1; }
rm -rf build/package build/pathly-module-monitoring-*.zip
mkdir -p build
composer archive --format=zip --dir=build --file="pathly-module-monitoring-$version" --no-interaction >/dev/null
mkdir -p build/package
cd build/package && unzip -q "../pathly-module-monitoring-$version.zip"
for forbidden in Test dev publication .github phpunit.xml.dist phpcs.xml.dist; do
  [ ! -e "$forbidden" ] || { echo "$forbidden ne doit pas être dans l'archive"; exit 1; }
done
[ -f composer.json ] && [ -f registration.php ] && [ -f etc/module.xml ] && [ -f LICENSE.txt ]
echo "build/pathly-module-monitoring-$version.zip ($(find . -type f | wc -l) fichiers)"
