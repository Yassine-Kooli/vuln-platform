"""Génère un rapport HTML autonome à partir d'une exécution (findings, résumé, métriques, comparaison).

Usage : python3 reporting/report.py results/<run>
Sortie : results/<run>/report.html (ouvrable dans un navigateur, sans dépendance externe).
"""
from __future__ import annotations

import html
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "scanners"))
from common import read_json  # noqa: E402

SEV_COLOR = {"critical": "#7f1d1d", "high": "#dc2626", "medium": "#d97706", "low": "#2563eb", "info": "#6b7280"}


def _e(v) -> str:
    return html.escape("" if v is None else str(v))


def _table(rows: list[dict], cols: list[str]) -> str:
    head = "".join(f"<th>{_e(c)}</th>" for c in cols)
    body = []
    for r in rows:
        cells = []
        for c in cols:
            v = r.get(c)
            if c == "severity":
                cells.append(f'<td><span class="sev" style="background:{SEV_COLOR.get(v, "#6b7280")}">{_e(v)}</span></td>')
            else:
                cells.append(f"<td>{_e(v)}</td>")
        body.append("<tr>" + "".join(cells) + "</tr>")
    return f"<table><thead><tr>{head}</tr></thead><tbody>{''.join(body)}</tbody></table>"


def build(run_dir: Path) -> Path:
    findings = read_json(run_dir / "findings.json")
    summary = read_json(run_dir / "summary.json")
    metrics = read_json(run_dir / "metrics.json") if (run_dir / "metrics.json").exists() else None
    comparison = read_json(run_dir / "comparison.json") if (run_dir / "comparison.json").exists() else None

    sections = [
        f"<h1>Rapport de sécurité — {_e(summary['target'])}</h1>",
        f"<p class='meta'>Exécution <code>{_e(summary['run'])}</code> · commit cible <code>{_e(summary['target_commit'])}</code> · "
        f"durée {_e(summary['duration_s'])} s · étapes : {_e(summary['stages'])}</p>",
        "<h2>Synthèse</h2>",
        "<div class='cards'>"
        + "".join(f"<div class='card'><b>{_e(v)}</b><span>{_e(k)}</span></div>"
                  for k, v in summary["by_approach"].items())
        + f"<div class='card'><b>{_e(summary['total_findings'])}</b><span>total</span></div></div>",
    ]

    if metrics:
        rows = [{"outil": t, **{k: m[k] for k in ("tp", "fp", "fn", "precision", "recall", "f1")}}
                for t, m in metrics["by_tool"].items()]
        sections += ["<h2>Métriques d'évaluation</h2>",
                     f"<p class='meta'>Vérité terrain : {metrics['ground_truth_size']} vulnérabilités connues. "
                     "FP = findings dont le CWE n'est pas dans la vérité terrain (valeur approchée, borne haute).</p>",
                     _table(rows, ["outil", "tp", "fp", "fn", "precision", "recall", "f1"])]

    if comparison:
        sections += ["<h2>Comparaison avec l'exécution précédente</h2>",
                     f"<p class='meta'>Nouvelles : <b>{len(comparison['new'])}</b> · "
                     f"Corrigées : <b>{len(comparison['fixed'])}</b> · Inchangées : <b>{len(comparison['unchanged'])}</b></p>",
                     "<h3>Nouvelles</h3>", _table(comparison["new"][:300], ["tool", "severity", "rule", "location"]),
                     "<h3>Corrigées</h3>", _table(comparison["fixed"][:300], ["tool", "severity", "rule", "location"])]

    sections += ["<h2>Findings</h2>",
                 "<input id='q' placeholder='Filtrer (outil, règle, emplacement)…' oninput='filt()'>",
                 "<div id='all'>" + _table(findings, ["tool", "approach", "severity", "cwe", "title", "location"]) + "</div>"]

    page = f"""<!doctype html><html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rapport {_e(summary['target'])}</title>
<style>
:root{{--fg:#111827;--muted:#6b7280;--line:#e5e7eb;--bg:#fff}}
@media (prefers-color-scheme: dark){{:root{{--fg:#f3f4f6;--muted:#9ca3af;--line:#374151;--bg:#111827}}}}
body{{font-family:system-ui,sans-serif;color:var(--fg);background:var(--bg);margin:16px;max-width:1200px}}
table{{border-collapse:collapse;width:100%;font-size:14px;margin:8px 0}}
th,td{{border-bottom:1px solid var(--line);padding:6px 8px;text-align:left;vertical-align:top;word-break:break-word}}
.sev{{color:#fff;padding:2px 8px;border-radius:4px;font-size:12px}}
.cards{{display:flex;gap:12px;flex-wrap:wrap}}
.card{{border:1px solid var(--line);border-radius:8px;padding:10px 16px;display:flex;flex-direction:column}}
.card b{{font-size:22px}} .meta{{color:var(--muted);font-size:14px}}
input{{width:100%;padding:8px;margin:8px 0;box-sizing:border-box}}
</style></head><body>
{''.join(sections)}
<script>
function filt(){{const q=document.getElementById('q').value.toLowerCase();
document.querySelectorAll('#all tbody tr').forEach(r=>{{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none'}});}}
</script></body></html>"""
    out = run_dir / "report.html"
    out.write_text(page, encoding="utf-8")
    return out


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(__doc__)
        sys.exit(2)
    print(f"Rapport : {build(Path(sys.argv[1]))}")
