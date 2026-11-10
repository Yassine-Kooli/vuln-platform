"""Évaluation des outils par rapport à une vérité terrain (vulnérabilités connues de la cible).

Usage : python3 metrics/evaluate.py results/<run> --target juice-shop

Définitions (approche par CWE, la vérité terrain étant décrite par CWE dans config/ground_truth.json) :
  VP (TP)  vulnérabilité connue détectée : une finding de l'outil a le CWE attendu
           (et, si précisé, un emplacement correspondant au motif 'location_pattern')
  FN       vulnérabilité connue non détectée
  FP       finding dont le CWE ne figure pas dans la vérité terrain de la cible
           (approximation : une finding 'hors vérité terrain' n'est pas forcément fausse)
  Précision = VP / (VP + FP) ; Rappel = VP / (VP + FN) ; F1 = moyenne harmonique.
Ces métriques sont calculées par outil ET par approche (SAST / DAST / SCA).
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "scanners"))
from common import ROOT, read_json, write_json  # noqa: E402


def _metrics(tp: int, fp: int, fn: int) -> dict:
    precision = tp / (tp + fp) if (tp + fp) else 0.0
    recall = tp / (tp + fn) if (tp + fn) else 0.0
    f1 = 2 * precision * recall / (precision + recall) if (precision + recall) else 0.0
    return {"tp": tp, "fp": fp, "fn": fn,
            "precision": round(precision, 3), "recall": round(recall, 3), "f1": round(f1, 3)}


def evaluate(findings: list[dict], truth: list[dict], group_by: str) -> dict:
    """Calcule les métriques pour chaque valeur de group_by ('tool' ou 'approach')."""
    truth_cwes = {t["cwe"] for t in truth}
    groups = sorted({f[group_by] for f in findings})
    results = {}
    for g in groups:
        subset = [f for f in findings if f[group_by] == g]
        detected = set()
        for t in truth:
            for f in subset:
                if f["cwe"] != t["cwe"]:
                    continue
                pattern = t.get("location_pattern")
                if pattern and not re.search(pattern, f["location"]):
                    continue
                detected.add(t["id"])
                break
        tp = len(detected)
        fn = len(truth) - tp
        fp = sum(1 for f in subset if f["cwe"] not in truth_cwes)
        results[g] = _metrics(tp, fp, fn)
        results[g]["detected"] = sorted(detected)
    return results


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("run_dir", type=Path)
    ap.add_argument("--target", required=True)
    args = ap.parse_args()

    gt_all = read_json(ROOT / "config" / "ground_truth.json")
    if args.target not in gt_all:
        print(f"Pas de vérité terrain pour {args.target}")
        return 2
    truth = gt_all[args.target]["vulnerabilities"]
    findings = read_json(args.run_dir / "findings.json")

    report = {
        "target": args.target,
        "ground_truth_size": len(truth),
        "by_tool": evaluate(findings, truth, "tool"),
        "by_approach": evaluate(findings, truth, "approach"),
    }
    write_json(args.run_dir / "metrics.json", report)
    for g, m in report["by_tool"].items():
        print(f"{g:12s} VP={m['tp']:2d} FP={m['fp']:4d} FN={m['fn']:2d} "
              f"précision={m['precision']:.2f} rappel={m['recall']:.2f} F1={m['f1']:.2f}")
    print(f"-> {args.run_dir / 'metrics.json'}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
