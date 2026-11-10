# Démonstration (scénario de soutenance, environ 10 minutes)

## 1. Déploiement de l'environnement (2 min)

```bash
./setup_targets.sh          # clone les sources épinglées, démarre Juice Shop (3000) et Roundcube (8090)
docker compose ps           # montrer les deux conteneurs up
```

Montrer dans un navigateur : http://localhost:3000 (Juice Shop) et http://localhost:8090 (Roundcube).
Mentionner crAPI : `./targets/deploy_crapi.sh up` (déploiement séparé, multi-services).

## 2. Exécution automatisée des analyses (3 min)

```bash
# Référence : règles communautaires seules
python3 pipeline.py --target juice-shop --stages sast,sca,dast --no-custom-rules --label base
# Version complète : + règles Semgrep maison
python3 pipeline.py --target juice-shop --stages sast,sca,dast --label complet
```

Montrer dans la console : les commandes exécutées (Semgrep, Trivy, npm audit, ZAP) et le résumé final.

## 3. Collecte et archivage (1 min)

```bash
ls results/                              # une exécution = un dossier horodaté
ls results/<run_complet>/raw/            # sorties brutes de chaque outil
cat results/<run_complet>/summary.json   # compte par outil, approche, sévérité
```

## 4. Exploitation : comparaison et métriques (2 min)

```bash
python3 reporting/compare.py results/<run_base> results/<run_complet>   # apport des règles maison
python3 metrics/evaluate.py results/<run_complet> --target juice-shop    # VP / FP / FN, précision, rappel
```

Points à souligner :
- les règles maison détectent des injections SQL dans `routes/login.ts` et `routes/search.ts`, ainsi que la clé privée codée en dur dans `lib/insecurity.ts`
- la précision est une borne haute : la vérité terrain est partielle et `data/static/codefixes/` contient du code volontairement vulnérable

## 5. Rapport et automatisation CI (2 min)

```bash
python3 reporting/report.py results/<run_complet>   # ouvrir results/<run_complet>/report.html
```

Montrer `.github/workflows/security.yml` : déclenchement sur modification du code, jobs SAST / SCA / DAST, fusion des résultats, artefacts archivés 90 jours.

## Questions probables

- **Pourquoi Juice Shop est-il si bruyant en SAST ?** Il contient du code de défi volontairement vulnérable, et la comparaison le montre.
- **Pourquoi `-port 8099` pour ZAP ?** Le proxy par défaut (8080) est déjà utilisé sur la machine de démonstration.
- **Peut-on mesurer les faux négatifs ?** Oui, pour les vulnérabilités présentes dans `config/ground_truth.json` ; la liste est à compléter.
