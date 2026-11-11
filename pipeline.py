"""Orchestrateur : enchaîne SAST, SCA, DAST, normalise et archive les résultats.

Usage :
  python3 pipeline.py --target juice-shop --stages sast,sca,dast [--label avant]
  python3 pipeline.py --target juice-shop --stages sast,sca --no-docker   # outils installés localement

Chaque exécution crée results/<horodatage>_<label>/ contenant :
  raw/          sorties brutes de chaque outil (semgrep.json, zap.json, trivy-fs.json, npm-audit.json, ...)
  findings.json findings normalisées (schéma commun)
  summary.json  compte par outil / approche / sévérité + métadonnées (commit, cible, durée)
"""
from __future__ import annotations

import argparse
import os
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT / "scanners"))
sys.path.insert(0, str(ROOT / "normalizer"))

from common import load_targets, new_run_dir, read_json, write_json  # noqa: E402
import sast_semgrep  # noqa: E402
import sast_sonarqube  # noqa: E402
import sca  # noqa: E402
import dast_zap  # noqa: E402
from normalize import dedupe, normalize, SEVERITY_ORDER  # noqa: E402

ALL_STAGES = ["sast", "sca", "dast"]


def git_commit(path: Path) -> str:
    try:
        out = subprocess.run(["git", "-C", str(path), "rev-parse", "--short", "HEAD"],
                             capture_output=True, text=True, check=True)
        return out.stdout.strip()
    except (subprocess.CalledProcessError, FileNotFoundError):
        return "unknown"


def main() -> int:
    ap = argparse.ArgumentParser(description="Pipeline de sécurité : SAST, SCA, DAST")
    ap.add_argument("--target", help="clé de config/targets.json")
    # Cible libre (utilisée par l'application web) : une URL et un dossier de code.
    ap.add_argument("--url", help="URL de la cible (DAST)")
    ap.add_argument("--source-dir", help="dossier du code source (SAST, SCA)")
    ap.add_argument("--image", help="image Docker de la cible (SCA image, optionnel)")
    ap.add_argument("--name", help="nom de la cible libre (avec --url)")
    ap.add_argument("--stages", default=",".join(ALL_STAGES))
    ap.add_argument("--label", default="scan", help="nom court de l'exécution (ex: avant, apres)")
    ap.add_argument("--no-docker", action="store_true", help="utiliser les outils installés localement")
    ap.add_argument("--no-custom-rules", action="store_true", help="SAST : sans les règles Semgrep maison")
    ap.add_argument("--with-sonar", action="store_true", help="SAST : ajouter SonarQube (serveur profil sonar requis)")
    args = ap.parse_args()

    if args.url:
        # Cible libre : on construit la configuration à la volée.
        if not args.source_dir:
            print("--source-dir est requis avec --url")
            return 2
        src = Path(args.source_dir).resolve()
        name = args.name or "custom"
        targets = {name: {
            "url": args.url,
            "source_dir": str(src),
            "npm_project": str(src) if (src / "package-lock.json").exists() else None,
            "docker_image": args.image,
        }}
        args.target = name
    elif not args.target:
        print("Indiquez --target ou --url")
        return 2
    else:
        targets = load_targets()
    if args.target not in targets:
        print(f"Cible inconnue : {args.target} (choix : {', '.join(targets)})")
        return 2
    tgt = targets[args.target]
    stages = [s.strip() for s in args.stages.split(",") if s.strip()]
    for s in stages:
        if s not in ALL_STAGES:
            print(f"Étape inconnue : {s}")
            return 2

    use_docker = not args.no_docker
    source_dir = (ROOT / tgt["source_dir"]).resolve()
    run_dir = new_run_dir(f"{args.target}_{args.label}")
    raw_dir = run_dir / "raw"
    raw_dir.mkdir()
    started = time.time()
    all_findings: list[dict] = []
    stage_status: dict[str, str] = {}
    print(f"== Exécution {run_dir.name} (cible {args.target}, étapes {stages})")

    def attempt(name: str, fn):
        """Une étape qui échoue n'arrête pas les autres : l'erreur est archivée dans le résumé."""
        print(f"== Étape : {name} | début", flush=True)
        try:
            fn()
            stage_status[name] = "ok"
            print(f"== Étape : {name} | ok", flush=True)
        except Exception as exc:  # noqa: BLE001
            stage_status[name] = f"error: {exc}"
            print(f"  !! {name} en erreur : {exc}", flush=True)
            print(f"== Étape : {name} | erreur", flush=True)

    if "sast" in stages:
        def _sast():
            if not source_dir.exists():
                raise RuntimeError(f"code source absent : {source_dir} (lancez setup_targets.sh)")
            f = sast_semgrep.scan(source_dir, raw_dir, use_docker=use_docker,
                                  custom_rules=not args.no_custom_rules)
            all_findings.extend(normalize("semgrep", read_json(f)))
        attempt("sast-semgrep", _sast)

        def _sast_sonar():
            f = sast_sonarqube.scan(source_dir, raw_dir)
            all_findings.extend(normalize("sonarqube", read_json(f)))
        if args.with_sonar:
            attempt("sast-sonarqube", _sast_sonar)

    if "sca" in stages:
        def _sca_fs():
            if not source_dir.exists():
                raise RuntimeError(f"code source absent : {source_dir}")
            f = sca.trivy_fs(source_dir, raw_dir, use_docker=use_docker)
            all_findings.extend(normalize("trivy-fs", read_json(f)))
        attempt("sca-trivy-fs", _sca_fs)

        def _sca_npm():
            f = sca.npm_audit(source_dir, raw_dir)
            all_findings.extend(normalize("npm-audit", read_json(f)))
        if tgt.get("npm_project"):
            attempt("sca-npm-audit", _sca_npm)

        if tgt.get("docker_image"):
            def _sca_img():
                f = sca.trivy_image(tgt["docker_image"], raw_dir, use_docker=use_docker)
                all_findings.extend(normalize("trivy-image", read_json(f)))
            attempt("sca-trivy-image", _sca_img)

    if "dast" in stages:
        def _dast():
            f = dast_zap.scan(tgt["url"], raw_dir)
            all_findings.extend(normalize("zap", read_json(f)))
        attempt("dast-zap", _dast)

    findings = dedupe(all_findings)
    findings.sort(key=lambda f: (SEVERITY_ORDER.index(f["severity"]), f["tool"], f["location"]))
    write_json(run_dir / "findings.json", findings)

    by = lambda key: {k: sum(1 for f in findings if f[key] == k) for k in sorted({f[key] for f in findings})}  # noqa: E731
    summary = {
        "run": run_dir.name,
        "target": args.target,
        "label": args.label,
        "target_commit": git_commit(source_dir) if source_dir.exists() else "unknown",
        "stages": stage_status,
        "duration_s": round(time.time() - started, 1),
        "total_findings": len(findings),
        "by_tool": by("tool"),
        "by_approach": by("approach"),
        "by_severity": by("severity"),
    }
    write_json(run_dir / "summary.json", summary)
    print(f"== Terminé : {len(findings)} findings -> {run_dir}")
    # L'application web lit ce fichier pour retrouver le dossier de l'exécution.
    path_file = os.environ.get("VP_RUN_PATH_FILE")
    if path_file:
        Path(path_file).write_text(str(run_dir), encoding="utf-8")
    return 0 if all(v == "ok" for v in stage_status.values()) else 1


if __name__ == "__main__":
    sys.exit(main())
