@extends('layout')
@section('title', 'Analyse '.$scan->id)
@section('head')
<style>
  .pbar{height:14px;border-radius:999px;background:var(--line);overflow:hidden}
  .pfill{height:100%;border-radius:999px;background:var(--run);transition:width .6s ease,background .3s}
  .pfill.running{background-image:linear-gradient(45deg,rgba(255,255,255,.22) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.22) 50%,rgba(255,255,255,.22) 75%,transparent 75%);background-size:20px 20px;animation:stripes 1s linear infinite}
  .pfill.done{background:var(--ok)} .pfill.done_with_errors{background:var(--warn)} .pfill.failed{background:var(--bad)}
  @keyframes stripes{from{background-position:0 0}to{background-position:20px 0}}
  .st{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
  .st.ok{background:#dcfce7;color:#14532d} .st.error{background:#fee2e2;color:#7f1d1d} .st.running{background:#dbeafe;color:#1e3a8a}
  @media (prefers-color-scheme: dark){:root:not([data-theme="light"]) .st.ok{background:#14532d;color:#dcfce7} :root:not([data-theme="light"]) .st.error{background:#7f1d1d;color:#fee2e2} :root:not([data-theme="light"]) .st.running{background:#1e3a8a;color:#dbeafe}}
  .stbl tr.ok td:first-child{border-left:3px solid var(--ok)} .stbl tr.error td:first-child{border-left:3px solid var(--bad)}
  .stbl tr.running td:first-child{border-left:3px solid var(--run)} .stbl tr.pending td:first-child{border-left:3px solid var(--line)}
  .stbl td{padding-left:10px}
  .stbl td:first-child{white-space:nowrap}
  .stbl td{vertical-align:top}
  .stbl td:first-child{white-space:nowrap}
  .stbl{vertical-align:top} .stbl td:first-child{white-space:nowrap} .stbl .cmds{font-size:11px;color:var(--muted);max-width:520px}
  .st.ok{color:var(--ok)} .st.error{color:var(--bad)} .st.running{color:var(--run)}
  .stbl tr.pending td{opacity:.55}
  .kv{display:grid;grid-template-columns:160px 1fr;gap:6px 14px;font-size:13px}
  .kv > div:nth-child(odd){color:var(--muted)} .kv .mono{word-break:break-all}
  #journal{max-height:440px;white-space:pre-wrap;word-break:break-word;margin:0}
  #journal .l-cmd{color:#93c5fd} #journal .l-step{color:#fde68a;font-weight:600} #journal .l-ok{color:#86efac}
  #journal .l-err{color:#fca5a5} #journal .l-head{color:#c4b5fd}
</style>
@endsection
@section('content')
@php
  $finished = $scan->isFinished();
  $summary = $scan->summary ?? [];
  $approaches = $summary['by_approach'] ?? [];
  $sevOrder = ['critical', 'high', 'medium', 'low', 'info'];
  $curFilter = ['approach' => request('approach'), 'severity' => request('severity'), 'q' => request('q')];
  $stateLabel = ['ok' => '✓ terminé', 'error' => '✗ erreur', 'running' => '● en cours'];
  $pipelineCmd = collect($live['log_tail'])->first(fn ($l) => str_starts_with($l, '  $ ') && str_contains($l, 'pipeline.py'));
  $duration = $scan->started_at ? gmdate('H:i:s', $scan->started_at->diffInSeconds($scan->finished_at ?? now())) : '—';
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
  <div>
    <h1>Analyse #{{ $scan->id }} · <a href="{{ route('targets.show', $scan->target) }}">{{ $scan->target->displayName() }}</a></h1>
    <div class="mono muted">{{ $scan->target->url }} · {{ strtoupper(implode(' + ', $scan->stages)) }} · {{ $scan->created_at->format('d/m/Y H:i') }}</div>
  </div>
  <div>@include('partials.status', ['status' => $scan->status])</div>
</div>

<div class="card" style="margin-top:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <b id="progLabel">{{ $finished ? 'Analyse terminée' : 'Analyse en cours' }}</b>
    <span id="progPct" class="mono">{{ $progress }}%</span>
  </div>
  <div class="pbar"><div class="pfill {{ $scan->status }}" id="progFill" style="width: {{ $progress }}%"></div></div>
</div>

@if ($scan->error)<div class="card" style="margin-top:16px;border-color:var(--bad);color:var(--bad)">{{ $scan->error }}</div>@endif

<div class="grid g-two" style="margin-top:20px">
  <div class="card">
    <h2>Étapes</h2>
    <table class="stbl">
      <thead><tr><th>Étape</th><th>État</th><th>Commandes exécutées</th></tr></thead>
      <tbody id="stageRows">
        @foreach ($live['stage_states'] as $st)
          <tr class="{{ $st['state'] }}">
            <td class="mono">{{ $st['name'] }}</td>
            <td><span class="st {{ $st['state'] }}">{{ $stateLabel[$st['state']] ?? $st['state'] }}</span></td>
            <td class="mono cmds">@foreach (array_slice($st['commands'], -2) as $c){{ $c }}<br>@endforeach</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <div class="cmd" id="curCmd" style="{{ $finished ? 'display:none' : '' }}">{{ $live['current_cmd'] ?: 'préparation…' }}</div>
  </div>

  <div class="card">
    <h2>Résumé</h2>
    @if ($finished && $overview)
      <div class="stats" style="margin:0 0 14px">
        <div class="stat"><b>{{ $summary['total_findings'] ?? 0 }}</b><span>findings</span></div>
        @foreach ($approaches as $name => $n)
          <div class="stat"><b>{{ $n }}</b><span>{{ $name }}</span></div>
        @endforeach
      </div>
      @include('partials.severity-bars', ['counts' => $overview['bySeverity']])
      @if ($scan->run_dir)
        <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
          @if (in_array('report.html', $reportFiles))<a class="btn" href="{{ route('scans.file', [$scan, 'report.html']) }}" target="_blank">Rapport HTML</a>@endif
          @if (in_array('comparison.md', $reportFiles))<a class="btn" href="{{ route('scans.file', [$scan, 'comparison.md']) }}" target="_blank">Comparaison</a>@endif
        </div>
      @endif
    @else
      <div class="empty">Les résultats apparaîtront à la fin de l'analyse.</div>
    @endif
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h2>Détails techniques</h2>
  <div class="kv">
    <div>Cible</div><div class="mono">{{ $scan->target->url }}</div>
    <div>Code source</div><div class="mono">{{ $scan->target->source_path ?? '—' }}</div>
    <div>Image Docker</div><div class="mono">{{ $scan->target->image ?? '—' }}</div>
    <div>Étapes demandées</div><div>{{ implode(', ', $scan->stages) }}</div>
    <div>Démarrée</div><div>{{ $scan->started_at?->format('d/m/Y H:i:s') ?? 'en file' }}</div>
    <div>Durée</div><div id="duration">{{ $duration }}</div>
    <div>Code de sortie</div><div>{{ $scan->exit_code ?? '—' }}</div>
    <div>Dossier de résultats</div><div class="mono">{{ $scan->run_dir ?? 'pas encore créé' }}</div>
    <div>Commande pipeline</div><div class="mono">{{ $pipelineCmd ? substr($pipelineCmd, 4) : '—' }}</div>
  </div>
</div>

@if ($finished && $overview)
  <div class="grid g-two" style="margin-top:16px">
    <div class="card">
      <h2>Par outil</h2>
      <table>
        <thead><tr><th>Outil</th><th>Approche</th><th class="num">Total</th>@foreach ($sevOrder as $s)<th class="num">{{ $s }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($overview['byTool'] as $tool => $row)
          <tr><td>{{ $tool }}</td><td>{{ $row['approach'] }}</td><td class="num"><b>{{ $row['total'] }}</b></td>
            @foreach ($sevOrder as $s)<td class="num">{{ $row['severity'][$s] ?? 0 }}</td>@endforeach</tr>
        @empty
          <tr><td colspan="8" class="empty">Aucun finding.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="card">
      <h2>Règles et CWE les plus fréquents</h2>
      <div class="grid g-two" style="gap:14px">
        <div><h3>Règles</h3><table><tbody>
          @foreach ($overview['topRules'] as $rule => $n)<tr><td class="loc">{{ $rule }}</td><td class="num">{{ $n }}</td></tr>@endforeach
        </tbody></table></div>
        <div><h3>CWE</h3><table><tbody>
          @forelse ($overview['topCwe'] as $cwe => $n)<tr><td class="mono">{{ $cwe }}</td><td class="num">{{ $n }}</td></tr>@empty<tr><td class="muted">aucun CWE</td><td></td></tr>@endforelse
        </tbody></table></div>
      </div>
    </div>
  </div>
@endif

@if ($finished && $scan->metrics)
  <div class="card" style="margin-top:16px">
    <h2>Métriques contre la vérité terrain</h2>
    <div class="hint" style="margin-bottom:10px">Vérité terrain partielle : la précision est une borne haute (voir le rapport).</div>
    <table>
      <thead><tr><th>Outil</th><th class="num">VP</th><th class="num">FN</th><th class="num">FP</th><th class="num">Précision</th><th class="num">Rappel</th><th class="num">F1</th></tr></thead>
      <tbody>
      @foreach ($scan->metrics['by_tool'] as $tool => $m)
        <tr><td>{{ $tool }}</td><td class="num">{{ $m['tp'] }}</td><td class="num">{{ $m['fn'] }}</td><td class="num">{{ $m['fp'] }}</td>
          <td class="num">{{ number_format($m['precision'], 2) }}</td><td class="num">{{ number_format($m['recall'], 2) }}</td><td class="num">{{ number_format($m['f1'], 2) }}</td></tr>
      @endforeach
      </tbody>
    </table>
  </div>
@endif

@if ($finished && $scan->comparison)
  <div class="card" style="margin-top:16px">
    <h2>Comparaison avec la référence</h2>
    <div class="muted" style="margin-bottom:10px">{{ count($scan->comparison['new']) }} nouvelles · {{ count($scan->comparison['fixed']) }} corrigées · {{ count($scan->comparison['unchanged']) }} inchangées</div>
    <div class="grid g-two" style="gap:14px">
      @foreach (['new' => 'Nouvelles', 'fixed' => 'Corrigées'] as $key => $label)
        <div><h3>{{ $label }}</h3><table><thead><tr><th>Outil</th><th>Sévérité</th><th>Emplacement</th></tr></thead><tbody>
          @forelse (array_slice($scan->comparison[$key], 0, 30) as $f)
            <tr><td>{{ $f['tool'] }}</td><td>{{ $f['severity'] }}</td><td class="loc">{{ $f['location'] }}</td></tr>
          @empty
            <tr><td colspan="3" class="muted">aucune</td></tr>
          @endforelse
        </tbody></table></div>
      @endforeach
    </div>
  </div>
@endif

@if ($finished)
<div class="card" style="margin-top:16px">
  <h2>Findings</h2>
  <form method="GET" class="filters">
    <div><label class="f" for="q">Recherche</label><input type="search" id="q" name="q" value="{{ $curFilter['q'] }}" placeholder="titre, règle, emplacement, CWE"></div>
    <div><label class="f" for="severity">Sévérité</label>
      <select id="severity" name="severity"><option value="">Toutes</option>
        @foreach ($sevOrder as $s)<option value="{{ $s }}" @selected($curFilter['severity'] === $s)>{{ $s }}</option>@endforeach
      </select></div>
    <div><label class="f" for="approach">Approche</label>
      <select id="approach" name="approach"><option value="">Toutes</option>
        @foreach (['SAST', 'SCA', 'DAST'] as $a)<option value="{{ $a }}" @selected($curFilter['approach'] === $a)>{{ $a }}</option>@endforeach
      </select></div>
    <div><button class="btn primary" type="submit">Filtrer</button></div>
  </form>
  <div class="hint" style="margin:10px 0">{{ $findings->total() }} résultat(s) · triés par sévérité</div>
  <div style="overflow:auto">
  <table>
    <thead><tr><th>Sévérité</th><th>Approche</th><th>Outil</th><th>CWE</th><th>Titre</th><th>Emplacement</th></tr></thead>
    <tbody>
    @forelse ($findings as $f)
      <tr>
        <td><span class="sev" style="background: var(--{{ $f->severity }})">{{ $f->severity }}</span></td>
        <td>{{ $f->approach }}</td><td>{{ $f->tool }}</td><td class="mono" style="white-space:nowrap">{{ $f->cwe ?? '—' }}</td>
        <td>{{ $f->title }}</td><td class="loc">{{ $f->location }}</td>
      </tr>
    @empty
      <tr><td colspan="6" class="empty">Aucun finding ne correspond aux filtres.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.pagination', ['paginator' => $findings])
</div>
@endif

<div class="card" style="margin-top:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
    <h2 style="margin:0">Journal technique</h2>
    <span class="hint" id="journalInfo">{{ count($live['log_tail']) }} lignes</span>
  </div>
  <pre class="log" id="journal" style="margin-top:12px">{{ implode("\n", $live['log_tail']) }}</pre>
</div>

<script>
(() => {
  const scanId = {{ $scan->id }};
  const plannedStages = @json($scan->stages);
  const finished = @json($finished);
  const esc = (v) => String(v).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const stateLabel = {ok: '✓ terminé', error: '✗ erreur', running: '● en cours'};
  const lineClass = (l) => {
    if (l.startsWith('  $ ')) return 'l-cmd';
    if (l.includes('== Étape')) return l.includes('| ok') ? 'l-ok' : (l.includes('| erreur') ? 'l-err' : 'l-step');
    if (l.includes('!!') || l.includes('Traceback') || l.includes('Error') || l.includes('ERROR')) return 'l-err';
    if (l.startsWith('==')) return 'l-head';
    return '';
  };

  function render(d) {
    const rows = d.stage_states.map(st => `<tr class="${esc(st.state)}"><td class="mono">${esc(st.name)}</td>
      <td><span class="st ${esc(st.state)}">${stateLabel[st.state] || esc(st.state)}</span></td>
      <td class="mono cmds">${(st.commands || []).slice(-2).map(esc).join('<br>')}</td></tr>`).join('');
    const seen = new Set(d.stage_states.map(s => s.name.split('-')[0]));
    const pending = d.finished ? '' : plannedStages.filter(g => !seen.has(g))
      .map(g => `<tr class="pending"><td class="mono">${g.toUpperCase()}</td><td>○ en attente</td><td></td></tr>`).join('');
    document.getElementById('stageRows').innerHTML = rows + pending;

    const cur = document.getElementById('curCmd');
    cur.style.display = d.finished ? 'none' : '';
    cur.textContent = d.current_cmd || 'préparation…';

    const fill = document.getElementById('progFill');
    fill.style.width = d.progress + '%';
    fill.className = 'pfill ' + (d.finished ? d.status : 'running');
    document.getElementById('progPct').textContent = d.progress + '%';
    document.getElementById('progLabel').textContent = d.finished ? 'Analyse terminée' : 'Analyse en cours';
    const pre = document.getElementById('journal');
    const atBottom = pre.scrollTop + pre.clientHeight >= pre.scrollHeight - 30;
    pre.innerHTML = d.log_tail.map(l => {
      const c = lineClass(l);
      return c ? `<span class="${c}">${esc(l)}</span>` : esc(l);
    }).join('\n');
    if (atBottom) pre.scrollTop = pre.scrollHeight;
    document.getElementById('journalInfo').textContent = `${d.log_tail.length} lignes · ${d.finished ? 'terminé' : 'en cours'}`;
  }

  async function tick() {
    try {
      const d = await (await fetch(`/scans/${scanId}/status`, {cache: 'no-store'})).json();
      render(d);
      if (!finished && d.finished) { location.reload(); }  // une fois terminé, on affiche le résumé complet
    } catch (e) { /* réseau momentanément coupé : nouvel essai au prochain tour */ }
  }

  tick();
  if (!finished) setInterval(tick, 2000);
})();
</script>
@endsection
