#!/bin/sh
# Fins de ligne LF sur les fichiers texte du module (postes Windows).
cd "$(dirname "$0")/.." && find . -path ./dev/tools -prune -o -path ./.git -prune -o -path ./build -prune -o -type f \( -name '*.php' -o -name '*.phtml' -o -name '*.xml' -o -name '*.dist' -o -name '*.js' -o -name '*.css' -o -name '*.json' -o -name '*.md' -o -name '*.sh' -o -name '*.csv' -o -name '*.yml' -o -name '*.Dockerfile' -o -name '*.txt' \) -print0 | xargs -0 sed -i 's/\r$//'
