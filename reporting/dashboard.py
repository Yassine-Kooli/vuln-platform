"""Tableau de bord statique : une page qui liste toutes les exécutions archivées dans results/.

Usage : python3 reporting/dashboard.py
Sortie : results/index.html (autonome, ouvrable directement dans un navigateur).
Aucune dépendance : il lit summary.json, metrics.json et comparison.json de chaque exécution.
"""
from __future__ import annotations

import html
import sys
from datetime import datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "scanners"))
from common import RESULTS_DIR, read_json  # noqa: E402

SEV_ORDER = ["critical", "high", "medium", "low", "info"]
SEV_COLOR = {"critical": "#7f1d1d", "high": "#dc2626", "medium": "#d97706", "low": "#2563eb", "info": "#6b7280"}


def _e(v) -> str:
    return html.escape("" if v is None else str(v))


def _run_row(run_dir: Path) -> dict | None:
    if not (run_dir / "summary.json").exists():
        return None
    s = read_json(run_dir / "summary.json")
    metrics = read_json(run_dir / "metrics.json") if (run_dir / "metrics.json").exists() else None
    comp = read_json(run_dir / "comparison.json") if (run_dir / "comparison.json").exists() else None
    stamp = datetime.strptime(run_dir.name[:15], "%Y%m%d-%H%M%S")
    stages = s.get("stages", {})
    failed = [k for k, v in stages.items() if v != "ok"]
    # Métriques par outil, ramenées à une ligne pour le tableau
    best = None
    if metrics:
        best = max(metrics["by_tool"].items(), key=lambda kv: kv[1]["f1"], default=None)
    return {
        "name": run_dir.name,
        "date": stamp,
        "target": s.get("target", ""),
        "label": s.get("label", ""),
        "total": s.get("total_findings", 0),
        "by_approach": s.get("by_approach", {}),
        "by_severity": s.get("by_severity", {}),
        "stages": stages,
        "failed": failed,
        "metrics": metrics,
        "best": best,
        "comparison": comp,
        "has_report": (run_dir / "report.html").exists(),
    }


def _badge(text: str, color: str) -> str:
    return f'<span class="badge" style="background:{color}">{_e(text)}</span>'


def _row_html(r: dict) -> str:
    status = (_badge("OK", "#15803d") if not r["failed"]
              else _badge("Erreur : " + ", ".join(r["failed"]), "#dc2626"))
    if not r["has_report"]:
        status += " " + _badge("partielle", "#6b7280")
    approaches = " · ".join(f"{k} {v}" for k, v in sorted(r["by_approach"].items()))
    sev = "".join(
        f'<span class="sev" style="background:{SEV_COLOR[k]}">{k} {r["by_severity"][k]}</span>'
        for k in SEV_ORDER if r["by_severity"].get(k)
    )
    if r["best"]:
        tool, m = r["best"]
        metric = f"{_e(tool)} : rappel {m['recall']:.2f}, précision {m['precision']:.2f}"
    else:
        metric = "—"
    links = []
    if r["has_report"]:
        links.append(f'<a href="{_e(r["name"])}/report.html">Rapport</a>')
    if r["comparison"]:
        links.append(f'<a href="{_e(r["name"])}/comparison.md">Comparaison</a>')
    links.append(f'<a href="{_e(r["name"])}/summary.json">JSON</a>')
    return (
        "<tr>"
        f"<td>{r['date']:%d/%m/%Y %H:%M}</td>"
        f"<td><b>{_e(r['target'])}</b><br><span class='muted'>{_e(r['label'])}</span></td>"
        f"<td>{status}</td>"
        f"<td class='num'><b>{r['total']}</b><br><span class='muted'>{_e(approaches)}</span></td>"
        f"<td>{sev}</td>"
        f"<td>{metric}</td>"
        f"<td class='links'>{' · '.join(links)}</td>"
        "</tr>"
    )


def build() -> Path:
    rows = []
    for d in sorted(RESULTS_DIR.iterdir(), reverse=True):
        if d.is_dir():
            r = _run_row(d)
            if r:
                rows.append(r)

    complete = [r for r in rows if r["has_report"] and not r["failed"]]
    latest = {}
    for r in rows:  # le plus récent par cible
        latest.setdefault(r["target"], r)

    cards = "".join(
        f'<div class="card"><span class="muted">{_e(t)}</span>'
        f'<b>{r["total"]}</b><span class="muted">findings · {r["date"]:%d/%m %H:%M}</span></div>'
        for t, r in latest.items()
    )
    table = "".join(_row_html(r) for r in rows)

    page = f"""<!doctype html><html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tableau de bord sécurité</title>
<style>
:root{{--fg:#111827;--muted:#6b7280;--line:#e5e7eb;--bg:#f9fafb;--card:#ffffff;--accent:#2563eb}}
@media (prefers-color-scheme: dark){{:root:not([data-theme="light"]){{--fg:#f3f4f6;--muted:#9ca3af;--line:#374151;--bg:#0f172a;--card:#1e293b;--accent:#60a5fa}}}}
:root[data-theme="dark"]{{--fg:#f3f4f6;--muted:#9ca3af;--line:#374151;--bg:#0f172a;--card:#1e293b;--accent:#60a5fa}}
body{{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,"Segoe UI",sans-serif}}
header{{padding:28px 32px 8px}} h1{{margin:0 0 4px;font-size:24px}} .sub{{color:var(--muted);font-size:14px}}
main{{padding:16px 32px 48px}}
.cards{{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0 24px}}
.card{{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px 18px;min-width:200px;display:flex;flex-direction:column;gap:2px}}
.card b{{font-size:26px}}
.toolbar{{display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap}}
input{{flex:1;min-width:220px;padding:9px 12px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--fg);font-size:14px}}
.wrap{{overflow-x:auto;background:var(--card);border:1px solid var(--line);border-radius:10px}}
table{{width:100%;border-collapse:collapse;font-size:14px}}
th,td{{padding:10px 12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}}
th{{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);background:var(--card);position:sticky;top:0}}
tr:last-child td{{border-bottom:none}} td.num{{white-space:nowrap}}
.muted{{color:var(--muted);font-size:12px}} .links a{{color:var(--accent);text-decoration:none}} .links a:hover{{text-decoration:underline}}
.badge{{color:#fff;padding:2px 8px;border-radius:999px;font-size:12px;margin-right:4px;display:inline-block;margin-bottom:2px}}
.sev{{color:#fff;padding:1px 6px;border-radius:4px;font-size:12px;margin-right:3px;display:inline-block;margin-bottom:2px}}
footer{{color:var(--muted);font-size:12px;padding:0 32px 32px}}
</style></head><body>
<header><h1>Tableau de bord sécurité</h1>
<div class="sub">{len(rows)} exécutions archivées · {len(complete)} complètes · mis à jour {datetime.now():%d/%m/%Y %H:%M}</div></header>
<main>
<div class="cards">{cards}</div>
<div class="toolbar"><input id="q" placeholder="Filtrer par cible, étiquette ou statut…" oninput="filt()"></div>
<div class="wrap"><table id="t"><thead><tr>
<th>Date</th><th>Cible</th><th>Statut</th><th>Findings</th><th>Sévérité</th><th>Meilleur outil (F1)</th><th>Rapports</th>
</tr></thead><tbody>{table}</tbody></table></div>
</main>
<footer>Généré par reporting/dashboard.py à partir de results/. Les exécutions « partielle » n'ont pas de rapport complet.</footer>
<script>
function filt(){{const q=document.getElementById('q').value.toLowerCase();
document.querySelectorAll('#t tbody tr').forEach(r=>{{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none'}});}}
</script></body></html>"""
    out = RESULTS_DIR / "index.html"
    out.write_text(page, encoding="utf-8")
    return out


if __name__ == "__main__":
    print(f"Tableau de bord : {build()}")
