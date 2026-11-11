#!/usr/bin/env bash
# Équivalent local du workflow .github/workflows/security.yml, sans GitHub Actions.
# Enchaîne les mêmes étapes : sources, analyses (SAST, SCA, DAST), métriques, rapport, comparaison.
#
# Usage : ./ci_local.sh [label]
# Pré-requis : docker compose up -d juice-shop   (Juice Shop doit répondre sur le port 3000)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
LABEL="${1:-local}"
TARGET="juice-shop"

echo "== 1. Sources des cibles"
python3 targets/fetch_sources.py --only "$TARGET"

echo "== 2. Vérification de la cible DAST"
curl -fs http://localhost:3000 >/dev/null || { echo "Juice Shop ne répond pas : docker compose up -d juice-shop"; exit 1; }

echo "== 3. Analyses SAST + SCA + DAST (une exécution archivée)"
set +e
python3 pipeline.py --target "$TARGET" --stages sast,sca,dast --label "$LABEL"
PIPE_STATUS=$?
set -e
RUN="$(ls -dt results/*_${TARGET}_${LABEL} | head -1)"
echo "Exécution : $RUN"

echo "== 4. Métriques et rapport"
python3 metrics/evaluate.py "$RUN" --target "$TARGET"
python3 reporting/report.py "$RUN"

echo "== 5. Comparaison avec la référence (baseline/)"
if [ -f baseline/findings.json ]; then
  mkdir -p results/baseline && cp baseline/findings.json results/baseline/
  python3 reporting/compare.py results/baseline "$RUN"
fi

echo "== 6. Tableau de bord"
python3 reporting/dashboard.py

echo "== Terminé (statut du pipeline : $PIPE_STATUS). Rapport : $RUN/report.html ; tableau de bord : results/index.html"
exit "$PIPE_STATUS"
