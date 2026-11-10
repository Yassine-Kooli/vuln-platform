#!/usr/bin/env bash
# Prépare l'environnement : code source des cibles (SAST/SCA) et conteneurs applicatifs (DAST).
# Usage : ./setup_targets.sh [--with-crapi]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

# 1. Code source des cibles, à la version épinglée dans config/targets.json.
python3 targets/fetch_sources.py

# 2. Applications DAST : Juice Shop et Roundcube via Docker Compose.
docker compose up -d

# 3. crAPI (optionnel, déploiement multi-services).
if [ "${1:-}" = "--with-crapi" ]; then
  ./targets/deploy_crapi.sh up
fi

echo "Cibles prêtes :"
echo "  Juice Shop : http://localhost:3000"
echo "  Roundcube  : http://localhost:8090"
