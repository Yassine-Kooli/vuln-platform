#!/usr/bin/env bash
# Déploie crAPI (OWASP) à partir de son dépôt officiel.
# Usage : ./targets/deploy_crapi.sh [up|down]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CRAPI_DIR="$ROOT/targets/crAPI"
CRAPI_REPO="https://github.com/OWASP/crAPI.git"
ACTION="${1:-up}"

if [ ! -d "$CRAPI_DIR/.git" ]; then
  git clone --depth 1 "$CRAPI_REPO" "$CRAPI_DIR"
fi

COMPOSE_FILE="$CRAPI_DIR/deploy/docker/docker-compose.yml"
OVERRIDE="$ROOT/targets/crapi-override.yml"
case "$ACTION" in
  up)   docker compose -f "$COMPOSE_FILE" -f "$OVERRIDE" --project-directory "$CRAPI_DIR/deploy/docker" up -d ;;
  down) docker compose -f "$COMPOSE_FILE" -f "$OVERRIDE" --project-directory "$CRAPI_DIR/deploy/docker" down ;;
  *)    echo "Usage: $0 [up|down]"; exit 1 ;;
esac

echo "crAPI : web http://localhost:8888 · MailHog http://localhost:8026"
