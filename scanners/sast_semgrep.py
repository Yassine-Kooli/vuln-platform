"""SAST : Semgrep, avec les règles communautaires ET les règles personnalisées du projet."""
from __future__ import annotations

from pathlib import Path

from common import ROOT, have, run

CUSTOM_RULES = ROOT / "rules" / "semgrep"
REGISTRY_CONFIGS = ["p/javascript", "p/typescript", "p/owasp-top-ten"]


def scan(source_dir: Path, out_dir: Path, use_docker: bool = True, custom_rules: bool = True) -> Path:
    """Lance Semgrep sur source_dir et écrit out_dir/semgrep.json. Renvoie ce chemin.

    custom_rules=False : seules les règles communautaires sont utilisées (référence pour mesurer l'apport des règles maison).
    """
    out_file = out_dir / "semgrep.json"

    if use_docker:
        # Le code et les règles sont montés dans le conteneur ; la sortie est écrite dans out_dir.
        cmd = ["docker", "run", "--rm",
               "-v", f"{source_dir.resolve()}:/src:ro",
               "-v", f"{CUSTOM_RULES.resolve()}:/rules:ro",
               "-v", f"{out_dir.resolve()}:/out",
               "-w", "/src", "semgrep/semgrep:latest", "semgrep", "scan"]
        for cfg in REGISTRY_CONFIGS:
            cmd += ["--config", cfg]
        if custom_rules:
            cmd += ["--config", "/rules"]
        cmd += ["--json", "--output", "/out/semgrep.json", "--metrics=off", "."]
    else:
        if not have("semgrep"):
            raise RuntimeError("semgrep introuvable : installez-le ou utilisez use_docker=True")
        cmd = ["semgrep", "scan"]
        for cfg in REGISTRY_CONFIGS:
            cmd += ["--config", cfg]
        if custom_rules:
            cmd += ["--config", str(CUSTOM_RULES)]
        cmd += ["--json", "--output", str(out_file), "--metrics=off", str(source_dir)]

    proc = run(cmd)
    # Semgrep renvoie 0 si aucun résultat, 1 si résultats avec --error ; 2+ = erreur réelle.
    if proc.returncode not in (0, 1):
        raise RuntimeError(f"Semgrep a échoué ({proc.returncode}) : {proc.stderr[-2000:]}")
    return out_file
