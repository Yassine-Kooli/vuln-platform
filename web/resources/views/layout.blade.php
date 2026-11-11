<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title', 'Plateforme de détection') · Vulnérabilités web</title>
@yield('head')
<style>
:root{--fg:#111827;--muted:#6b7280;--line:#e5e7eb;--bg:#f3f4f6;--card:#fff;--accent:#2563eb;--ok:#15803d;--warn:#d97706;--bad:#dc2626;--run:#2563eb;
--critical:#7f1d1d;--high:#dc2626;--medium:#d97706;--low:#2563eb;--info:#6b7280}
@media (prefers-color-scheme: dark){:root:not([data-theme="light"]){--fg:#f3f4f6;--muted:#9ca3af;--line:#374151;--bg:#0f172a;--card:#1e293b;--accent:#60a5fa}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,"Segoe UI",sans-serif;font-size:14px;line-height:1.45}
a{color:var(--accent);text-decoration:none} a:hover{text-decoration:underline}
nav{background:var(--card);border-bottom:1px solid var(--line);padding:14px 28px;display:flex;align-items:center;gap:22px}
nav .brand{font-weight:700;font-size:16px;color:var(--fg)} nav .brand:hover{text-decoration:none}
nav .sp{flex:1} nav .muted{color:var(--muted)}
main{max-width:1280px;margin:0 auto;padding:24px 28px 60px}
h1{font-size:22px;margin:0 0 4px} h2{font-size:16px;margin:0 0 12px} h3{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:16px 0 8px}
.sub{color:var(--muted);margin:0 0 20px}
.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px}
.grid{display:grid;gap:16px}
.g-targets{grid-template-columns:repeat(auto-fill,minmax(300px,1fr))}
.g-two{grid-template-columns:1fr 1fr} @media (max-width:900px){.g-two{grid-template-columns:1fr}}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:16px 0 24px}
.stat{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px}
.stat b{display:block;font-size:24px} .stat span{color:var(--muted);font-size:12px}
.btn{display:inline-block;border:1px solid var(--line);background:var(--card);color:var(--fg);padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-family:inherit}
.btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
.btn.danger{color:var(--bad)}
.btn:hover{text-decoration:none;filter:brightness(.97)}
form.inline{display:inline}
input[type=text],input[type=url],input[type=search],select{width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--fg);font:inherit}
label.f{display:block;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;margin:14px 0 5px}
.hint{color:var(--muted);font-size:12px;margin-top:5px}
.checks{display:flex;gap:16px;flex-wrap:wrap} .checks label{display:flex;gap:6px;align-items:center;text-transform:none;color:var(--fg);font-size:14px;margin:0}
.err{color:var(--bad);font-size:13px;margin-top:4px}
.flash{background:#dcfce7;color:#14532d;border:1px solid #86efac;padding:10px 14px;border-radius:8px;margin-bottom:16px}
.pill{display:inline-block;padding:2px 10px;border-radius:999px;color:#fff;font-size:12px;white-space:nowrap;background:var(--muted)}
.pill.done{background:var(--ok)} .pill.done_with_errors{background:var(--warn)} .pill.failed{background:var(--bad)}
.pill.running{background:var(--run)} .pill.queued{background:var(--muted)}
.sev{display:inline-block;color:#fff;padding:1px 8px;border-radius:4px;font-size:12px;margin-right:4px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{font-size:11px;text-transform:uppercase;color:var(--muted);letter-spacing:.04em;font-weight:600}
td.loc,.mono{font-family:ui-monospace,Consolas,monospace;font-size:12px;word-break:break-all}
td.num{text-align:right;font-variant-numeric:tabular-nums}
.bar{display:grid;grid-template-columns:70px 1fr 50px;gap:8px;align-items:center;font-size:12px;margin:5px 0}
.bar .t{background:var(--line);border-radius:6px;height:10px;overflow:hidden} .bar .f{height:100%;border-radius:6px}
.snap{width:100%;border-radius:10px;border:1px solid var(--line);background:var(--bg);display:block}
.snap-empty{border:1px dashed var(--line);border-radius:10px;padding:40px 16px;text-align:center;color:var(--muted);background:var(--bg)}
.stages{border:1px solid var(--line);border-radius:10px;overflow:hidden}
.srow{display:grid;grid-template-columns:24px 1fr auto;gap:8px;align-items:center;padding:9px 12px;border-bottom:1px solid var(--line)}
.srow:last-child{border-bottom:none}
.srow .ico{font-weight:700;text-align:center} .srow .sn{font-family:ui-monospace,Consolas,monospace;font-size:13px}
.srow .ss{font-size:12px;color:var(--muted)}
.srow.ok .ico,.srow.ok .ss{color:var(--ok)} .srow.error .ico,.srow.error .ss{color:var(--bad)}
.srow.running .ico,.srow.running .ss{color:var(--run)} .srow.pending{opacity:.55}
.cmd{font-family:ui-monospace,Consolas,monospace;font-size:12px;color:var(--muted);border:1px dashed var(--line);border-radius:8px;padding:8px 10px;margin-top:10px;word-break:break-all}
pre.log{background:#0b1020;color:#d1d5db;padding:12px;border-radius:8px;max-height:320px;overflow:auto;font-size:12px;white-space:pre-wrap}
.pager{display:flex;gap:8px;align-items:center;justify-content:center;margin-top:12px}
.empty{color:var(--muted);text-align:center;padding:24px}
</style>
</head>
<body>
<nav>
  <a class="brand" href="{{ route('dashboard') }}">Vulnérabilités web</a>
  <a href="{{ route('dashboard') }}">Cibles</a>
  <a href="{{ route('targets.create') }}">+ Ajouter une cible</a>
  <span class="sp"></span>
  <span class="muted">SAST · SCA · DAST</span>
</nav>
<main>
  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
  @yield('content')
</main>
</body>
</html>
