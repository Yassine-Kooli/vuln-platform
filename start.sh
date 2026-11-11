#!/usr/bin/env bash
# Démarre la plateforme : interface web (Laravel) + file de travail des analyses.
# Usage : ./start.sh   puis ouvrir http://127.0.0.1:8000
set -euo pipefail
cd "$(dirname "$0")/web"
php artisan migrate --force >/dev/null
php artisan vp:recover
php artisan storage:link >/dev/null 2>&1 || true
trap 'kill 0' EXIT INT TERM
# Analyses en parallèle : VP_WORKERS travailleurs (3 par défaut, une par cible).
# Chaque analyse DAST a son propre port ZAP ; surveillez la mémoire (ZAP consomme plusieurs Go).
for _ in $(seq 1 "${VP_WORKERS:-3}"); do
  php artisan queue:work --queue=default --tries=1 --timeout=0 &
done
# File séparée pour les captures : elles n'attendent pas derrière une analyse.
php artisan queue:work --queue=snapshots --tries=1 --timeout=0 &
php artisan serve --host=127.0.0.1 --port=8000
