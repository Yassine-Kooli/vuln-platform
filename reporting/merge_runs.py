"""Fusionne les findings de plusieurs exécutions (ex. jobs SAST / SCA / DAST de la CI) en une seule.

Usage : python3 reporting/merge_runs.py <dossier_entrée> <dossier_sortie>
Le dossier d'entrée contient des sous-dossiers, chacun avec un findings.json et un summary.json.
Sortie : findings.json (dédupliqué) et summary.json recalculé.
"""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "scanners"))
sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "normalizer"))
from common import read_json, write_json  # noqa: E402
from normalize import SEVERITY_ORDER, dedupe  # noqa: E402


def merge(in_dir: Path, out_dir: Path) -> dict:
    findings: list[dict] = []
    sources: list[str] = []
    stages: dict[str, str] = {}
    for f in sorted(in_dir.rglob("findings.json")):
        findings += read_json(f)
        sources.append(f.parent.name)
        summ = f.parent / "summary.json"
        if summ.exists():
            stages.update(read_json(summ).get("stages", {}))
    merged = dedupe(findings)
    merged.sort(key=lambda x: (SEVERITY_ORDER.index(x["severity"]), x["tool"], x["location"]))

    def count(key: str) -> dict:
        return {k: sum(1 for x in merged if x[key] == k) for k in sorted({x[key] for x in merged})}

    summary = {
        "run": out_dir.name,
        "target": "juice-shop",
        "label": "fusion",
        "target_commit": "see-sources",
        "stages": stages,
        "sources": sources,
        "duration_s": 0,
        "total_findings": len(merged),
        "by_tool": count("tool"),
        "by_approach": count("approach"),
        "by_severity": count("severity"),
    }
    out_dir.mkdir(parents=True, exist_ok=True)
    write_json(out_dir / "findings.json", merged)
    write_json(out_dir / "summary.json", summary)
    return summary


if __name__ == "__main__":
    if len(sys.argv) != 3:
        print(__doc__)
        sys.exit(2)
    s = merge(Path(sys.argv[1]), Path(sys.argv[2]))
    print(f"Fusion : {s['total_findings']} findings depuis {len(s['sources'])} source(s)")
