"""Comparaison de deux exécutions (ex. avant / après intégration d'une correction ou d'un outil).

Usage : python3 reporting/compare.py results/<run_avant> results/<run_apres>
Sortie : comparison.json et comparison.md dans le dossier de la 2e exécution.
"""
from __future__ import annotations

import sys
from collections import Counter
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "scanners"))
from common import read_json, write_json  # noqa: E402


def compare(before: list[dict], after: list[dict]) -> dict:
    b = {f["id"]: f for f in before}
    a = {f["id"]: f for f in after}
    new = [a[i] for i in a if i not in b]
    fixed = [b[i] for i in b if i not in a]
    kept = [a[i] for i in a if i in b]
    return {
        "before_total": len(before),
        "after_total": len(after),
        "new": new,
        "fixed": fixed,
        "unchanged": kept,
        "new_by_tool": dict(Counter(f["tool"] for f in new)),
        "fixed_by_tool": dict(Counter(f["tool"] for f in fixed)),
    }


def to_markdown(result: dict, before_name: str, after_name: str) -> str:
    lines = [
        f"# Comparaison : `{before_name}` → `{after_name}`",
        "",
        f"- Findings avant : **{result['before_total']}**",
        f"- Findings après : **{result['after_total']}**",
        f"- Nouvelles : **{len(result['new'])}** · Corrigées : **{len(result['fixed'])}** · Inchangées : **{len(result['unchanged'])}**",
        "",
    ]
    for title, key in [("Nouvelles findings", "new"), ("Findings corrigées", "fixed")]:
        lines += [f"## {title}", "", "| Outil | Sévérité | Règle | Emplacement |", "|---|---|---|---|"]
        for f in result[key][:200]:
            lines.append(f"| {f['tool']} | {f['severity']} | {f['rule']} | {f['location']} |")
        lines.append("")
    return "\n".join(lines)


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__)
        return 2
    run_before, run_after = Path(sys.argv[1]), Path(sys.argv[2])
    result = compare(read_json(run_before / "findings.json"), read_json(run_after / "findings.json"))
    write_json(run_after / "comparison.json", result)
    (run_after / "comparison.md").write_text(to_markdown(result, run_before.name, run_after.name), encoding="utf-8")
    print(f"Nouvelles : {len(result['new'])} | Corrigées : {len(result['fixed'])} | "
          f"Inchangées : {len(result['unchanged'])}  -> {run_after / 'comparison.md'}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
