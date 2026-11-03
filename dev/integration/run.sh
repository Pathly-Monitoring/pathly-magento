#!/usr/bin/env bash
# Test d'intégration du module sur un vrai Magento, API Pathly simulée.
#
#   dev/integration/run.sh            installe, teste, puis arrête et efface tout
#   dev/integration/run.sh --keep     garde la boutique en marche (captures d'écran)
#
# Prérequis : Docker, curl, openssl, et l'archive construite (dev/build.sh).
# Image Magento : MAGENTO_IMAGE (construite depuis magento.Dockerfile si absente).
# Mémoire : environ 3 Go pour la base, OpenSearch et Magento. Un seul Magento à la fois.
set -eu  # sans pipefail : « grep -q » ferme ses tubes tôt, c'est voulu
export MSYS_NO_PATHCONV=1

DIR="$(cd "$(dirname "$0")" && (pwd -W 2>/dev/null || pwd))"
ROOT="$(cd "$DIR/../.." && (pwd -W 2>/dev/null || pwd))"
STATE="$DIR/.state"
PORT="${MAGENTO_PORT:-8617}"
BASE="http://localhost:$PORT"
ADMIN="admin_it"
KEY="sp_integration_key_0123456789"
FLOW="9e8d7c6b-5a49-4382-8170-6f5e4d3c2b1a"
KEEP=0
[ "${1:-}" = "--keep" ] && KEEP=1

dc() { docker compose -p pathly-magento-it -f "$DIR/docker-compose.yml" "$@"; }
mg() { dc exec -T -u www-data magento php -d memory_limit=2G bin/magento "$@"; }
sql() { dc exec -T db mariadb -uroot -pintegration magento -N -e "$1"; }
requests() { cat "$STATE/api/requests.jsonl" 2>/dev/null || true; }
count() { requests | grep -c -- "$1" || true; }
step() { printf '\n== %s\n' "$*"; }
ok() { printf '   ok : %s\n' "$*"; }
fail() { printf '   ÉCHEC : %s\n' "$*" >&2; [ -n "${2:-}" ] && printf '%s\n' "$2" >&2; exit 1; }
expect_count() { # motif, nombre attendu, message
  local n; n=$(count "$1"); [ "$n" = "$2" ] || fail "$3 (attendu $2, trouvé $n)" "$(requests)"; ok "$3"; }
cleanup() {
  if [ "$KEEP" = 0 ]; then dc down -v --remove-orphans >/dev/null 2>&1 || true; fi
}
trap cleanup EXIT

[ -f "$ROOT/build/package/registration.php" ] || fail "archive absente : lancer dev/build.sh"

step "Simulateur de l'API (https://api.pathlyhq.com) et certificat de test"
rm -rf "$STATE"; mkdir -p "$STATE/certs" "$STATE/api"; chmod 777 "$STATE/api"
openssl req -x509 -newkey rsa:2048 -nodes -days 2 -subj "/CN=api.pathlyhq.com" \
  -addext "subjectAltName=DNS:api.pathlyhq.com" \
  -keyout "$STATE/certs/api.key" -out "$STATE/certs/api.crt" >/dev/null 2>&1
chmod 644 "$STATE/certs/api.key"
ok "certificat créé"

step "Démarrage de MariaDB, OpenSearch, du simulateur et de Magento"
if ! docker image inspect "${MAGENTO_IMAGE:-pathly-magento-it:2.4.8-p3}" >/dev/null 2>&1; then
  dc build magento
fi
dc up -d --wait db opensearch api
dc up -d magento
dc cp "$STATE/certs/api.crt" magento:/usr/local/share/ca-certificates/pathly-api.crt
dc exec -T -u root magento update-ca-certificates >/dev/null
ok "le magasin fait confiance au certificat du simulateur"

step "Installation de Magento"
mg setup:install --base-url="$BASE/" --db-host=db --db-name=magento --db-user=root \
  --db-password=integration --admin-firstname=Test --admin-lastname=Pathly \
  --admin-email=test@example.com --admin-user=pathly --admin-password=integration-2026 \
  --backend-frontname="$ADMIN" --language=fr_FR --currency=EUR --timezone=Europe/Paris \
  --use-rewrites=1 --search-engine=opensearch --opensearch-host=opensearch --opensearch-port=9200 \
  --cleanup-database >/dev/null
mg config:set admin/security/use_form_key 0 >/dev/null
mg config:set admin/security/session_lifetime 86400 >/dev/null
mg config:set admin/usage/enabled 0 >/dev/null
MAGENTO_VERSION_RUNNING=$(mg --version | sed 's/.*CLI //')
ok "Magento $MAGENTO_VERSION_RUNNING installé"

step "Installation du module (archive dépliée dans app/code)"
dc exec -T -u root magento mkdir -p /var/www/html/app/code/Pathly/Monitoring
dc cp "$ROOT/build/package/." magento:/var/www/html/app/code/Pathly/Monitoring
dc exec -T -u root magento chown -R www-data:www-data /var/www/html/app/code/Pathly
mg module:enable Pathly_Monitoring >/dev/null
mg setup:upgrade --keep-generated >/dev/null
mg cache:flush >/dev/null
mg module:status Pathly_Monitoring | grep -q "Module is enabled" || fail "module non activé"
ok "module activé"
[ "$(sql "SELECT COUNT(*) FROM patch_list WHERE patch_name LIKE 'Pathly%'")" = 1 ] || fail "patch de désinstallation non enregistré"
ok "patch réversible enregistré"

step "Rien n'est envoyé avant la configuration"
set +e; mg pathly:sync >/dev/null; code=$?; set -e
[ "$code" = 1 ] || fail "pathly:sync devait répondre « non connecté »"
mg cron:run >/dev/null 2>&1 || true
[ "$(requests | wc -l | tr -d ' ')" = 0 ] || fail "requête envoyée avant la configuration" "$(requests)"
ok "aucune requête vers Pathly"

step "Clé refusée : rien n'est enregistré"
set +e; out=$(mg config:set pathly_monitoring/connection/api_key sp_wrong_key_0123456789 2>&1); code=$?; set -e
[ "$code" != 0 ] || fail "une clé refusée a été enregistrée"
grep -q "refused the API key\|a refusé la clé" <<<"$out" || fail "message de refus absent" "$out"
[ -z "$(sql "SELECT value FROM core_config_data WHERE path='pathly_monitoring/connection/api_key'")" ] || fail "clé refusée en base"
ok "clé refusée, message lisible, rien en base"

step "Clé valide : chiffrée, puis Ping créé"
mg config:set pathly_monitoring/connection/api_key "$KEY" >/dev/null
stored=$(sql "SELECT value FROM core_config_data WHERE path='pathly_monitoring/connection/api_key'")
[ -n "$stored" ] && [ "$stored" != "$KEY" ] && ! grep -q "$KEY" <<<"$stored" || fail "clé non chiffrée en base"
ok "clé chiffrée en base"
expect_count '"uri":"/v1/account"' 2 "compte vérifié par GET /v1/account (clé refusée puis valide)"
expect_count '"method":"POST","uri":"/v1/scenarios"' 1 "un Ping créé"
requests | grep '"method":"POST","uri":"/v1/scenarios"' | grep -q "\"url\":\"$BASE/\"" || fail "Ping sans l'adresse de la boutique"
requests | grep '"method":"POST","uri":"/v1/scenarios"' | grep -q '"idempotencyKey":"magento-ping-' || fail "Ping sans clé d'idempotence"
requests | grep -q "\"userAgent\":\"pathly-magento/[0-9.]* Magento/" || fail "versions absentes de l'en-tête"
ok "Ping sur $BASE/, avec clé d'idempotence et versions dans l'en-tête"
mg config:set pathly_monitoring/connection/api_key "$KEY" >/dev/null
expect_count '"method":"POST","uri":"/v1/scenarios"' 1 "clé enregistrée deux fois : Ping rattaché, sans doublon"

step "Lumi depuis l'administration (session, jeton du formulaire)"
jar="$STATE/cookies.txt"
login_page=$(curl -sL -c "$jar" -b "$jar" "$BASE/$ADMIN/")
form_key=$(grep -m1 -o 'name="form_key" type="hidden" value="[^"]*"' <<<"$login_page" | sed 's/.*value="//; s/"$//')
[ -n "$form_key" ] || fail "jeton du formulaire de connexion introuvable"
curl -s -o "$STATE/curl.out" -c "$jar" -b "$jar" -X POST "$BASE/$ADMIN/" \
  --data-urlencode "form_key=$form_key" --data-urlencode "login[username]=pathly" \
  --data-urlencode "login[password]=integration-2026"
dashboard=$(curl -sL -c "$jar" -b "$jar" "$BASE/$ADMIN/admin/dashboard/")
admin_key=$(grep -m1 -o "FORM_KEY = '[^']*'" <<<"$dashboard" | sed "s/FORM_KEY = '//; s/'$//")
[ -n "$admin_key" ] || fail "connexion à l'administration impossible"
ok "connecté à l'administration"
post() { curl -s -c "$jar" -b "$jar" -X POST "$BASE/$ADMIN/pathly/lumi/$1/" --data-urlencode "form_key=$admin_key" -d "isAjax=true" "${@:2}"; }
anon=$(curl -s -X POST "$BASE/$ADMIN/pathly/lumi/start/" -d "confirm_owner=1")
! grep -q '"ok"' <<<"$anon" && grep -q 'login' <<<"$anon" || fail "action Lumi ouverte sans session" "$anon"
ok "action Lumi refusée sans session (page de connexion)"
out=$(post start)
grep -q '"ok":false' <<<"$out" || fail "Lumi lancé sans consentement" "$out"
out=$(post start -d confirm_owner=1)
grep -q '"ok":true' <<<"$out" || fail "Lumi non lancé" "$out"
trial=$(grep -o '"trialId":"[^"]*"' <<<"$out" | sed 's/.*:"//; s/"$//')
requests | grep '/v1/flow-assistant/detect' | grep -q "\"body\":{\"url\":\"$BASE/\"}" || fail "detect sans l'adresse seule"
requests | grep '/v1/flow-assistant/trials"' | grep -q '"confirmOwner":true' || fail "essai sans confirmOwner"
ok "essai $trial lancé, seule l'adresse de la boutique envoyée"
out=$(post status -d "trial=$trial")
grep -q '"done":true' <<<"$out" && grep -q '"kind":"cart"' <<<"$out" || fail "propositions absentes" "$out"
ok "deux Flow proposés (panier, connexion)"
out=$(post save -d "trial=$trial" -d index=0)
grep -q "\"flowId\":\"$FLOW\"" <<<"$out" || fail "Flow non enregistré" "$out"
out=$(post save -d "trial=$trial" -d index=0)
grep -q "\"flowId\":\"$FLOW\"" <<<"$out" || fail "double validation mal gérée" "$out"
out=$(post save -d "trial=$trial" -d index=1)
grep -q '"ok":false' <<<"$out" && grep -q 'Solo' <<<"$out" || fail "limite de l'offre mal affichée" "$out"
ok "Flow validé en un clic, sans doublon, limite de l'offre affichée"

step "Encart du tableau de bord"
dashboard=$(curl -sL -c "$jar" -b "$jar" "$BASE/$ADMIN/admin/dashboard/")
for text in "pathly-panel" "pathly-fail" "public/run-shot/" "Boutique test\|Solo"; do
  grep -q "$text" <<<"$dashboard" || fail "encart : « $text » absent"
done
ok "état du Ping et du Flow, dernier échec avec capture, offre"

step "Rejeu après setup:upgrade"
before=$(count "/v1/scenarios/$FLOW/run")
mg setup:upgrade --keep-generated >/dev/null
[ "$(count "/v1/scenarios/$FLOW/run")" = "$before" ] || fail "rejeu envoyé pendant setup:upgrade"
ok "setup:upgrade note seulement la demande"
mg maintenance:enable >/dev/null
out=$(mg pathly:sync); grep -q '"result":"maintenance"' <<<"$out" || fail "rejeu pendant la maintenance" "$out"
mg maintenance:disable >/dev/null
ok "attente de la fin du mode maintenance"
out=$(mg pathly:sync); grep -q '"result":"replayed"' <<<"$out" || fail "Flow non rejoué" "$out"
expect_count "\"method\":\"POST\",\"uri\":\"/v1/scenarios/$FLOW/run\"" $((before + 1)) "Flow rejoué par POST /v1/scenarios/{id}/run"

step "Mise à jour d'un module (empreinte des versions)"
dc exec -T -u root magento mkdir -p /var/www/html/app/code/Test
dc cp "$DIR/dummy" magento:/var/www/html/app/code/Test/Dummy
dc exec -T -u root magento chown -R www-data:www-data /var/www/html/app/code/Test
mg module:enable Test_Dummy >/dev/null
out=$(mg pathly:sync); grep -q '"result":"too_soon"' <<<"$out" || fail "changement de module non vu" "$out"
dc exec -T -u www-data magento sed -i 's/1.0.0/1.0.1/' app/code/Test/Dummy/composer.json
out=$(mg pathly:sync); grep -q '"result":"too_soon"' <<<"$out" || fail "nouvelle version non vue" "$out"
grep -q modules <<<"$(sql "SELECT flag_data FROM flag WHERE flag_code='pathly_monitoring_state'")" || fail "demande de rejeu absente"
ok "nouvelle version vue, rejeu demandé, 10 minutes au moins entre deux rejeux"
mg setup:upgrade --keep-generated >/dev/null
mg cron:run >/dev/null 2>&1 || true
[ "$(sql "SELECT COUNT(*) FROM cron_schedule WHERE job_code='pathly_monitoring_sync'")" -ge 1 ] || fail "tâche planifiée absente"
ok "tâche planifiée pathly_monitoring_sync programmée"

step "Badge de disponibilité"
code=$(curl -s -o "$STATE/curl.out" -w '%{http_code}' "$BASE/pathly/badge/svg")
[ "$code" = 404 ] || fail "badge servi alors qu'il est éteint (HTTP $code)"
ok "éteint par défaut (404)"
mg config:set pathly_monitoring/badge/enabled 1 >/dev/null
mg config:set pathly_monitoring/badge/footer 1 >/dev/null
mg cache:flush >/dev/null
requests | grep '"method":"POST","uri":"/v1/status-pages"' | grep -q '"public":true' || fail "page de statut non publiée"
headers=$(curl -s -D - -o "$STATE/badge.svg" "$BASE/pathly/badge/svg")
grep -qi "content-type: image/svg+xml" <<<"$headers" && grep -q "<svg" "$STATE/badge.svg" || fail "badge non servi" "$headers"
curl -s "$BASE/" | grep -q "pathly/badge/svg" || fail "badge absent du pied de page"
ok "page publiée, badge servi par la boutique et affiché en pied de page"
mg config:set pathly_monitoring/badge/enabled 0 >/dev/null
requests | grep '"method":"PATCH"' | grep -q '"public":false' || fail "page de statut non dépubliée"
code=$(curl -s -o "$STATE/curl.out" -w '%{http_code}' "$BASE/pathly/badge/svg")
[ "$code" = 404 ] || fail "badge encore servi"
ok "éteint : page dépubliée (publiée par le module), 404"

step "Aucune clé dans les journaux"
if dc exec -T -u www-data magento sh -c "grep -rl '$KEY' var/log var/report 2>/dev/null"; then fail "clé trouvée dans un journal"; fi
ok "var/log et var/report sans la clé"

if [ "$KEEP" = 1 ]; then
  step "Boutique gardée en marche pour les captures : $BASE/$ADMIN/ (pathly / integration-2026)"
  exit 0
fi

step "Désinstallation (module copié dans app/code)"
mg module:disable Pathly_Monitoring >/dev/null
mg module:uninstall --non-composer Pathly_Monitoring >/dev/null
for query in "SELECT COUNT(*) FROM core_config_data WHERE path LIKE 'pathly_monitoring/%'" \
  "SELECT COUNT(*) FROM flag WHERE flag_code LIKE 'pathly_monitoring%'" \
  "SELECT COUNT(*) FROM cron_schedule WHERE job_code LIKE 'pathly_monitoring%'"; do
  [ "$(sql "$query")" = 0 ] || fail "donnée restante : $query"
done
ok "réglages, état et tâches planifiées effacés"

printf '\nIntégration réussie sur Magento %s.\n' "$MAGENTO_VERSION_RUNNING"
