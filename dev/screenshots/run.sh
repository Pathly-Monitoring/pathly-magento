#!/usr/bin/env bash
# Captures du dossier de publication. Prérequis : dev/integration/run.sh --keep.
#   dev/screenshots/run.sh fr   (puis en)
set -eu  # sans pipefail : « grep -q » ferme ses tubes tôt, c'est voulu
export MSYS_NO_PATHCONV=1
DIR="$(cd "$(dirname "$0")" && (pwd -W 2>/dev/null || pwd))"
ROOT="$(cd "$DIR/../.." && (pwd -W 2>/dev/null || pwd))"
lang="${1:-fr}"
locale=$([ "$lang" = fr ] && echo fr_FR || echo en_US)
docker compose -p pathly-magento-it -f "$ROOT/dev/integration/docker-compose.yml" exec -T db \
  mariadb -uroot -pintegration magento -e "UPDATE admin_user SET interface_locale='$locale'"
docker run --rm --network pathly-magento-it_it -v "$DIR:/work" -v "$ROOT/publication/screenshots:/out" \
  -w /tmp mcr.microsoft.com/playwright:v1.51.0-jammy \
  sh -c "npm init -y >/dev/null && npm i -s playwright@1.51.0 >/dev/null && cp /work/capture.mjs . && node capture.mjs $lang /out"
