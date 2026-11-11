# Plateforme d'automatisation de la détection de vulnérabilités web

Plateforme reproductible qui combine trois approches de test de sécurité sur des applications web volontairement vulnérables, compare leur efficacité et centralise les résultats.

| Approche | Outil(s) | Cible |
|---|---|---|
| **SAST** (analyse statique) | Semgrep (règles communautaires + règles maison) | Code source de OWASP Juice Shop |
| **SCA** (dépendances) | Trivy (système de fichiers et image Docker), npm audit | Dépendances de Juice Shop |
| **DAST** (analyse dynamique) | OWASP ZAP (spider, spider AJAX, scan actif) | Juice Shop (et Roundcube, crAPI en option) |
| **CI/CD** | GitHub Actions | Déclenchement à chaque modification |

Cibles : **OWASP Juice Shop**, **crAPI**, **Roundcube webmail**.

## Arborescence

```
docker-compose.yml        Déploiement de Juice Shop et Roundcube
setup_targets.sh          Préparation : code source + conteneurs
targets/                  Clonage des sources (fetch_sources.py) et déploiement de crAPI (deploy_crapi.sh)
config/targets.json       Cibles : URL, dépôt, version épinglée, emplacement
config/ground_truth.json  Vérité terrain (vulnérabilités connues) pour les métriques
scanners/                 SAST (sast_semgrep.py), SCA (sca.py), DAST (dast_zap.py)
zap/automation.yaml       Plan ZAP (spider, AJAX, scan actif, rapports)
rules/semgrep/            Règles Semgrep personnalisées (mécanisme complémentaire)
normalizer/normalize.py   Conversion des sorties de chaque outil vers un schéma commun
pipeline.py               Orchestrateur : exécute les étapes, archive les résultats
reporting/                Comparaison avant/après, fusion (CI), rapport HTML
metrics/evaluate.py       VP / FP / FN, précision, rappel, F1 par outil et par approche
.github/workflows/        Pipeline GitHub Actions
tests/                    Tests unitaires (stdlib) et jeu d'essai pour les règles
results/                  Une exécution = un dossier horodaté (sorties brutes, findings, rapport)
```

## Prérequis

- Docker (les outils SAST, SCA et DAST s'exécutent en conteneurs, aucune installation locale requise)
- Python 3.10+ (bibliothèque standard uniquement)
- Git, Node.js/npm (pour `npm audit`)

## Utilisation pas à pas

```bash
# 1. Déployer les cibles (sources + Juice Shop + Roundcube)
./setup_targets.sh                 # ajouter --with-crapi pour crAPI

# 2. Lancer une analyse complète (SAST + SCA + DAST) ; chaque exécution est archivée
python3 pipeline.py --target juice-shop --stages sast,sca,dast --label complet

# 3. Comparer deux exécutions (ex. sans puis avec les règles maison)
python3 pipeline.py --target juice-shop --stages sast,sca,dast --no-custom-rules --label base
python3 reporting/compare.py results/<run_base> results/<run_complet>

# 4. Métriques et rapport HTML
python3 metrics/evaluate.py results/<run_complet> --target juice-shop
python3 reporting/report.py results/<run_complet>   # -> results/<run_complet>/report.html

# 5. Tests unitaires
python3 -m unittest discover -s tests -v
```

Chaque exécution produit dans `results/<horodatage>_<cible>_<label>/` :

- `raw/` : sorties brutes des outils (`semgrep.json`, `zap.json` + `zap.html`, `trivy-fs.json`, `trivy-image.json`, `npm-audit.json`), et le plan ZAP exécuté (`zap-plan.yaml`)
- `findings.json` : toutes les findings normalisées (schéma commun)
- `summary.json` : compte par outil, approche et sévérité ; étapes en erreur éventuelles
- `metrics.json`, `comparison.json`/`.md`, `report.html` selon les étapes lancées

## Schéma commun d'une finding

| Champ | Contenu |
|---|---|
| `id` | empreinte stable (outil + règle + emplacement sans numéro de ligne) : permet la comparaison d'une exécution à l'autre |
| `tool`, `approach` | outil (semgrep, zap, trivy-fs, trivy-image, npm-audit) et approche (SAST, DAST, SCA) |
| `rule`, `title` | identifiant de la règle et libellé |
| `severity` | critical, high, medium, low, info (normalisée) |
| `location` | fichier:emplacement (SAST), URL [+ paramètre] (DAST), paquet@version (SCA) |
| `cwe` | CWE normalisé (ex. `CWE-89`) |

## Mécanisme de détection personnalisé

Fichier : `rules/semgrep/custom-web-rules.yml` — 6 règles, validées avec `semgrep --validate` et testées sur un jeu d'essai (`tests/fixtures/sample/`, un fichier vulnérable et un fichier sain) :

| Règle | Détecte | CWE |
|---|---|---|
| `custom-sequelize-query-concat` | requête SQL par concaténation passée à `sequelize.query()` | CWE-89 |
| `custom-sequelize-query-template-literal` | requête SQL en template literal interpolé | CWE-89 |
| `custom-hardcoded-private-key` | clé privée PEM codée en dur | CWE-321 |
| `custom-eval-non-literal` | `eval()` avec une valeur non littérale | CWE-95 |
| `custom-xml-external-entities` | parseur XML avec `noent: true` | CWE-611 |
| `custom-open-redirect-user-input` | redirection dont la cible vient de la requête | CWE-601 |

Le fichier sain ne produit aucune alerte.

## Métriques

`metrics/evaluate.py` compare les findings à `config/ground_truth.json` :

- **VP** : vulnérabilité connue détectée (même CWE, et emplacement correspondant si un motif est défini)
- **FN** : vulnérabilité connue non détectée
- **FP** : finding dont le CWE n'est pas dans la vérité terrain
- **Précision** = VP / (VP + FP), **Rappel** = VP / (VP + FN), **F1** = moyenne harmonique

**Limites à mentionner lors de la soutenance** :

- La vérité terrain est partielle (6 vulnérabilités de Juice Shop). Les FP sont donc une **borne haute** : une finding « hors vérité terrain » peut être une vraie vulnérabilité non répertoriée.
- Juice Shop contient un dossier `data/static/codefixes/` de code volontairement vulnérable (défis). Les règles y déclenchent des findings que la vérité terrain ne compte pas comme FP.
- Pour crAPI et Roundcube, la vérité terrain est vide : les métriques ne sont calculées que pour Juice Shop.

## Pipeline GitHub Actions

Fichier : `.github/workflows/security.yml`.

- **Déclenchement** : push sur `main` modifiant le code d'analyse, les règles ou la configuration ; toute pull request ; lancement manuel.
- **Jobs** : `sast`, `sca`, `dast` (Juice Shop démarré dans le job, ZAP, puis arrêt) ; `rapport` fusionne les résultats, calcule les métriques, génère le rapport HTML et, si `baseline/findings.json` existe, la comparaison.
- **Archivage** : chaque job publie ses résultats en artefact (conservation 90 jours).

## Dépannage

- **Port déjà utilisé** : modifier le port hôte dans `docker-compose.yml` (et `config/targets.json`). Ici Roundcube est sur 8090 car 8080 et 8081 étaient occupés.
- **ZAP « Address already in use »** : le proxy de ZAP utilise par défaut le port 8080 ; le lancement passe `-port 8099`.
- **`npm audit` impossible** : Juice Shop ne versionne pas de `package-lock.json`. Pour l'analyse, générer le lockfile dans la copie clonée : `npm install --package-lock-only --ignore-scripts` (fait dans `targets/src/juice-shop`, jamais dans ce dépôt).
- **Permissions sur `results/`** : le conteneur ZAP écrit en tant qu'autre utilisateur ; le dossier de sortie est passé en `777` par `dast_zap.py`.

## Tableau de bord

Une page regroupe toutes les exécutions : cible, statut de chaque étape, nombre de findings par approche et sévérité, meilleur outil, liens vers le rapport, la comparaison et le JSON.

```bash
python3 reporting/dashboard.py     # génère results/index.html
```

`ci_local.sh` le régénère en fin d'exécution. Les exécutions marquées « partielle » ne contiennent pas de rapport complet (essais intermédiaires).

## Résultats des exécutions de référence

Exécutions archivées dans `results/` (chaque dossier contient `report.html`) :

| Cible | Exécution | Analyses | Findings | Métriques |
|---|---|---|---|---|
| Juice Shop | `*_juice-shop_base` | SAST, SCA, DAST, sans règles maison | 488 | Semgrep rappel 4/6 |
| Juice Shop | `*_juice-shop_final` | SAST, SCA, DAST, avec règles maison (référence CI : `baseline/`) | 503 | Semgrep rappel 5/6, précision 0,28 |
| crAPI | `*_crapi_final` | SAST, SCA, DAST | 366 | Rappel 0,60 en SCA, 0 en SAST et DAST |
| Roundcube | `*_roundcube_avant` (SAST, SCA) + `*_roundcube_avant` (DAST, run séparé) | SAST, SCA (image), DAST | 1863 + 38 | Pas de vérité terrain |

Présentation : `docs/presentation.pptx` (13 diapositives, suit les quatre points de la démonstration). Guide de démonstration : `docs/demo.md`.

**Limites connues** : vérité terrain partielle et à valider (Juice Shop et crAPI) ; DAST sans authentification ; Roundcube sans `composer.lock`, donc `trivy-fs` ne trouve rien ; SonarQube non intégré.

## SonarQube (SAST, optionnel)

Serveur local : `docker compose --profile sonar up -d sonarqube` (port 9000). Jeton dans `.sonar-token` (ignoré par git).
Analyse : `python3 pipeline.py --target juice-shop --stages sast --with-sonar --label sonar`.

Dernier résultat (Juice Shop, run `*_juice-shop_sonar`) : 152 issues, 2 des 6 vulnérabilités de la vérité terrain détectées (rappel 0,33, précision 0,02). Le CWE de chaque règle est lu dans la description publiée par SonarQube (lien cwe.mitre.org) ; certaines règles n'en publient qu'une catégorie générique (ex. CWE-1240).

SonarQube n'est pas dans le workflow GitHub Actions : il demande un serveur dédié et un réglage système (`vm.max_map_count`) que les runners hébergés ne fournissent pas.

## Interface web (Laravel)

Une application web pour gérer tout le projet : ajouter une cible (URL, code source, image Docker optionnelle), capturer une image de la page, lancer SAST / SCA / DAST, suivre l'exécution, et consulter les findings, métriques, comparaisons et rapports. Toutes les données (cibles, analyses, étapes, findings) sont enregistrées dans une base SQLite.

```bash
cd web && php artisan migrate        # une seule fois
./start.sh                            # interface + file de travail des analyses
# ouvrir http://127.0.0.1:8000
```

- **Une analyse à la fois** : la file de travail exécute les analyses l'une après l'autre (ZAP et Docker ne supportent pas deux scans simultanés).
- **Captures** : prises avec Google Chrome en mode headless (`VP_CHROME` pour un autre chemin).
- **Résultats** : chaque analyse garde son dossier dans `results/` (comme en ligne de commande) et ses findings dans la base.
- **Tests** : `cd web && php artisan test`.
- **Sécurité** : le serveur n'écoute que sur 127.0.0.1. Ne scannez que des applications que vous êtes autorisé à tester.
