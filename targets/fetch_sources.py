"""Clone le code source de chaque cible à la version épinglée dans config/targets.json (reproductibilité).

Usage : python3 targets/fetch_sources.py [--only juice-shop,roundcube]
Un dépôt déjà présent est laissé en place (pour forcer la mise à jour, supprimez le dossier).
"""
from __future__ import annotations

import argparse
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "scanners"))
from common import load_targets  # noqa: E402


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--only", default="", help="liste de cibles séparées par des virgules")
    args = ap.parse_args()
    wanted = [w for w in args.only.split(",") if w]

    for name, cfg in load_targets().items():
        if wanted and name not in wanted:
            continue
        # crAPI a son propre déploiement (targets/deploy_crapi.sh) ; on ne le clone pas ici.
        if not cfg.get("source_repo") or name == "crapi":
            continue
        dest = ROOT / cfg["source_dir"]
        if (dest / ".git").exists():
            print(f"-> {name} : déjà présent ({dest})")
            continue
        dest.parent.mkdir(parents=True, exist_ok=True)
        print(f"-> {name} : clone {cfg['source_repo']}@{cfg['source_ref']}")
        subprocess.run(["git", "clone", "--depth", "1", "--branch", cfg["source_ref"],
                        cfg["source_repo"], str(dest)], check=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
