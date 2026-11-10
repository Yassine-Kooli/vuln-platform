"""SCA : analyse des dépendances avec Trivy (système de fichiers + image) et npm audit."""
from __future__ import annotations

from pathlib import Path

from common import have, run


def trivy_fs(source_dir: Path, out_dir: Path, use_docker: bool = True) -> Path:
    """Trivy sur le système de fichiers : lockfiles npm, composer, etc. -> trivy-fs.json."""
    out_file = out_dir / "trivy-fs.json"
    if use_docker:
        cmd = ["docker", "run", "--rm",
               "-v", f"{source_dir.resolve()}:/src:ro",
               "-v", f"{out_dir.resolve()}:/out",
               "aquasec/trivy:latest", "fs", "--scanners", "vuln",
               "--format", "json", "--output", "/out/trivy-fs.json", "/src"]
    else:
        if not have("trivy"):
            raise RuntimeError("trivy introuvable")
        cmd = ["trivy", "fs", "--scanners", "vuln", "--format", "json",
               "--output", str(out_file), str(source_dir)]
    proc = run(cmd)
    if proc.returncode != 0:
        raise RuntimeError(f"Trivy fs a échoué : {proc.stderr[-2000:]}")
    return out_file


def trivy_image(image: str, out_dir: Path, use_docker: bool = True) -> Path:
    """Trivy sur une image Docker (dépendances OS + applicatives) -> trivy-image.json."""
    out_file = out_dir / "trivy-image.json"
    if use_docker:
        cmd = ["docker", "run", "--rm",
               "-v", "/var/run/docker.sock:/var/run/docker.sock",
               "-v", f"{out_dir.resolve()}:/out",
               "aquasec/trivy:latest", "image", "--scanners", "vuln",
               "--format", "json", "--output", "/out/trivy-image.json", image]
    else:
        if not have("trivy"):
            raise RuntimeError("trivy introuvable")
        cmd = ["trivy", "image", "--scanners", "vuln", "--format", "json",
               "--output", str(out_file), image]
    proc = run(cmd)
    if proc.returncode != 0:
        raise RuntimeError(f"Trivy image a échoué : {proc.stderr[-2000:]}")
    return out_file


def npm_audit(project_dir: Path, out_dir: Path) -> Path:
    """npm audit --json sur un projet npm (nécessite package-lock.json) -> npm-audit.json."""
    out_file = out_dir / "npm-audit.json"
    if not (project_dir / "package-lock.json").exists():
        raise RuntimeError(f"Pas de package-lock.json dans {project_dir}")
    proc = run(["npm", "audit", "--json"], cwd=project_dir)
    # npm audit renvoie un code non nul dès qu'il trouve une vulnérabilité : ce n'est pas une erreur.
    if not proc.stdout.strip():
        raise RuntimeError(f"npm audit n'a rien renvoyé : {proc.stderr[-2000:]}")
    out_file.write_text(proc.stdout, encoding="utf-8")
    return out_file
