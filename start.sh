#!/usr/bin/env bash
# Démarre la plateforme : interface web (Laravel) + file de travail des analyses.
# Usage : ./start.sh   puis ouvrir http://127.0.0.1:8000
set -euo pipefail
cd "$(dirname "$0")/web"
trap 'kill 0' EXIT INT TERM
php artisan queue:work --queue=default --tries=1 --timeout=0 &
# File séparée pour les captures : elles ne attendent pas derrière une analyse.
php artisan queue:work --queue=snapshots --tries=1 --timeout=0 &
php artisan serve --host=127.0.0.1 --port=8000
