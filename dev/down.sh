#!/usr/bin/env bash
# Détruit l'environnement jetable : conteneurs ET volumes (base, fichiers GLPI).
set -euo pipefail
cd "$(dirname "$0")"
docker compose down -v --remove-orphans
rm -f .env.test
