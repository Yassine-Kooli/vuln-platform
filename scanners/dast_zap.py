"""DAST : OWASP ZAP piloté par le plan d'automatisation zap/automation.yaml."""
from __future__ import annotations

from pathlib import Path

from common import ROOT, run

ZAP_IMAGE = "zaproxy/zap-stable:latest"
ZAP_TEMPLATE = ROOT / "zap" / "automation.yaml"


def scan(target_url: str, out_dir: Path) -> Path:
    """Lance ZAP sur target_url. Produit out_dir/zap.json et out_dir/zap.html."""
    out_dir = out_dir.resolve()
    out_dir.mkdir(parents=True, exist_ok=True)
    # L'utilisateur du conteneur ZAP n'est pas l'utilisateur hôte : il doit pouvoir écrire ici.
    out_dir.chmod(0o777)

    # 1. Rendu du plan : on injecte l'URL cible dans le gabarit, et on dépose le plan
    #    dans le dossier de sortie (ainsi le plan exécuté est archivé avec les résultats).
    plan = ZAP_TEMPLATE.read_text(encoding="utf-8").replace("__TARGET_URL__", target_url)
    (out_dir / "zap-plan.yaml").write_text(plan, encoding="utf-8")

    # 2. Exécution de ZAP en conteneur. --network host : la cible est sur localhost.
    cmd = [
        "docker", "run", "--rm", "--network", "host",
        "-v", f"{out_dir}:/zap/wrk:rw",
        # -port : --network host partage le réseau de l'hôte ; le proxy par défaut (8080) peut être occupé.
        ZAP_IMAGE, "zap.sh", "-cmd", "-port", "8099", "-autorun", "/zap/wrk/zap-plan.yaml",
    ]
    proc = run(cmd)
    # Codes ZAP : 0 = OK, 1 = alertes/avertissements, 2 = erreur de plan. Les autres = échec.
    if proc.returncode not in (0, 1, 2):
        raise RuntimeError(f"ZAP a échoué ({proc.returncode}) : {proc.stderr[-2000:]}")

    report = out_dir / "zap.json"
    if not report.exists():
        raise RuntimeError("ZAP n'a pas produit zap.json (voir le plan et la sortie console)")
    return report
