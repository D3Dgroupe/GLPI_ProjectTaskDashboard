#!/usr/bin/env bash
# Démarre le GLPI 11.0.12 jetable (MariaDB + GLPI + plugin monté), l'installe, sème les données de test
# et écrit dev/.env.test. Idempotent : si l'environnement est déjà semé, ne fait que le (re)démarrer.
# Variables : PLUGIN_SRC (checkout du plugin à monter, défaut = ce dépôt), GLPI_PORT (défaut 8088),
# WITH_FIELDS=1 (optionnel : installe aussi le plugin Fields 1.24.6 + un conteneur ProjectTask Priorité/Module, voir README).
set -euo pipefail
cd "$(dirname "$0")"
HERE="$PWD"
export PLUGIN_SRC="$(cd "${PLUGIN_SRC:-..}" && pwd)"
export GLPI_PORT="${GLPI_PORT:-8088}"
PLUGIN=projecttaskdashboard
SVC=glpi
dc() { docker compose "$@"; }
gx() { dc exec -T -w /var/www/glpi "$SVC" "$@"; }

dc up -d --wait --wait-timeout 180 2>&1 | tail -3 || true

echo "Attente de GLPI (installation automatique de la base)…"
for i in $(seq 1 120); do
  if curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$GLPI_PORT/api.php/status" 2>/dev/null | grep -q '^[234]'; then
    gx test -f /var/glpi/config/config_db.php && gx bin/console db:check --quiet >/dev/null 2>&1 && break
  fi
  sleep 3
done

FIELDS_VERSION=1.24.6
# Opt-in : le dossier plugins/fields vit dans le conteneur (pas dans un volume) ; l'étape est donc rejouée à chaque up.sh.
install_fields() {
  [ "${WITH_FIELDS:-0}" = 1 ] || return 0
  if ! gx test -f plugins/fields/setup.php; then
    gx bash -c "curl -sSfL -o /tmp/fields.tbz https://github.com/pluginsGLPI/fields/releases/download/$FIELDS_VERSION/glpi-fields-$FIELDS_VERSION.tar.bz2 \
      && tar -xjf /tmp/fields.tbz -C /var/www/glpi/plugins && chown -R www-data:www-data /var/www/glpi/plugins/fields"
  fi
  gx bin/console plugin:install fields -u glpi -n 2>&1 | tail -1 || true
  gx bin/console plugin:activate fields 2>&1 | tail -1 || true
  gx php < "$HERE/fields-setup.php" | tail -n 1
}

BASE="http://127.0.0.1:$GLPI_PORT"
if gx test -f /var/glpi/ptd-seed.done 2>/dev/null; then
  [ -f .env.test ] || { echo "Environnement déjà semé mais .env.test absent : lancer dev/down.sh puis dev/up.sh." >&2; exit 1; }
  install_fields
  echo "Déjà semé ; .env.test conservé. (down.sh puis up.sh pour repartir de zéro.)"
  exit 0
fi

gx bin/console config:set enable_hlapi 1 >/dev/null
gx bin/console plugin:install "$PLUGIN" -u glpi --force -n
gx bin/console plugin:activate "$PLUGIN"

rnd() { openssl rand -hex 8; }
TECH_LOGIN=ptd_tech; TECH_PW="Tp-$(rnd)"
RO_LOGIN=ptd_ro;     RO_PW="Rp-$(rnd)"
gx bin/console user:create "$TECH_LOGIN" -p "$TECH_PW" -n
gx bin/console user:create "$RO_LOGIN" -p "$RO_PW" -n

SEED_JSON="$(dc exec -T -w /var/www/glpi -e PTD_TECH_LOGIN="$TECH_LOGIN" -e PTD_RO_LOGIN="$RO_LOGIN" "$SVC" \
  php /opt/ptd/seed.php | tail -n 1)"
echo "$SEED_JSON" | python3 -c 'import json,sys; json.load(sys.stdin)' # échoue si ce n'est pas du JSON

j() { echo "$SEED_JSON" | python3 -c "import json,sys; d=json.load(sys.stdin); v=d['$1']; print(','.join(map(str,v)) if isinstance(v,list) else v)"; }
umask 077
cat > .env.test <<ENV
# Généré par dev/up.sh — identifiants jetables, ne pas committer.
GLPI_BASE_URL=$BASE
GLPI_API_V2_URL=$BASE/api.php/v2.3
GLPI_OAUTH_TOKEN_URL=$BASE/api.php/token
GLPI_OAUTH_CLIENT_ID=$(j oauth_client_id)
GLPI_OAUTH_CLIENT_SECRET=$(j oauth_client_secret)
GLPI_ADMIN_LOGIN=glpi
GLPI_ADMIN_PASSWORD=glpi
GLPI_TECH_LOGIN=$TECH_LOGIN
GLPI_TECH_PASSWORD=$TECH_PW
GLPI_TECH_USER_ID=$(j user_tech_id)
GLPI_RO_LOGIN=$RO_LOGIN
GLPI_RO_PASSWORD=$RO_PW
GLPI_RO_USER_ID=$(j user_ro_id)
GLPI_ENTITY_OTHER_ID=$(j entity_other_id)
GLPI_PROJECT_PARENT_ID=$(j project_parent_id)
GLPI_PROJECT_CHILD_A_ID=$(j project_child_a_id)
GLPI_PROJECT_CHILD_B_ID=$(j project_child_b_id)
GLPI_TASK_IDS=$(j task_ids)
GLPI_PROJECT_OTHER_ENTITY_ID=$(j project_other_id)
GLPI_TASK_OTHER_ENTITY_ID=$(j task_other_id)
ENV
install_fields
gx touch /var/glpi/ptd-seed.done
echo "OK : $BASE (admin glpi/glpi). Identifiants de test dans dev/.env.test"
