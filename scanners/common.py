"""Utilitaires communs à tous les scanners : exécution de commandes, arborescence des résultats."""
from __future__ import annotations

import json
import shutil
import subprocess
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RESULTS_DIR = ROOT / "results"


def run(cmd: list[str], cwd: Path | None = None, check: bool = False) -> subprocess.CompletedProcess:
    """Exécute une commande et renvoie le résultat (stdout/stderr capturés)."""
    print("  $ " + " ".join(str(c) for c in cmd))
    return subprocess.run(cmd, cwd=cwd, capture_output=True, text=True, check=check)


def have(binary: str) -> bool:
    return shutil.which(binary) is not None


def new_run_dir(label: str) -> Path:
    """Crée results/<horodatage>_<label>/ : chaque exécution est archivée dans son propre dossier."""
    stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    path = RESULTS_DIR / f"{stamp}_{label}"
    path.mkdir(parents=True, exist_ok=True)
    return path


def write_json(path: Path, data) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False), encoding="utf-8")


def read_json(path: Path):
    return json.loads(path.read_text(encoding="utf-8"))


def load_targets() -> dict:
    return read_json(ROOT / "config" / "targets.json")["targets"]
