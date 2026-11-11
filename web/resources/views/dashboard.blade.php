@extends('layout')
@section('title', 'Cibles')
@section('content')
<h1>Cibles</h1>
<p class="sub">Chaque cible a sa capture d'écran, son code source et son historique d'analyses.</p>

<div class="stats">
  <div class="stat"><b>{{ $targets->count() }}</b><span>cibles</span></div>
  <div class="stat"><b>{{ $totalScans }}</b><span>analyses</span></div>
  <div class="stat"><b>{{ $totalFindings }}</b><span>findings enregistrés</span></div>
  <div class="stat"><b>{{ $runningScans }}</b><span>en cours ou en file</span></div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
  <h2 style="margin:0">Mes cibles</h2>
  <div style="display:flex;gap:8px">
    @if ($targets->isNotEmpty())
      <form class="inline" method="POST" action="{{ route('scans.all') }}">@csrf
        <button class="btn" type="submit" title="SAST + SCA + DAST sur chaque cible, en parallèle" onclick="return confirm('Lancer SAST + SCA + DAST sur toutes les cibles ? Les analyses tournent en parallèle.')">Analyser toutes les cibles</button></form>
    @endif
    <a class="btn primary" href="{{ route('targets.create') }}">+ Ajouter une cible</a>
  </div>
</div>

@if ($targets->isEmpty())
  <div class="card empty">Aucune cible pour l'instant. Ajoutez une application pour lancer une analyse.</div>
@else
  <div class="grid g-targets">
    @foreach ($targets as $target)
      @php
        $last = $target->latestScan;
        $tone = ['done' => 'ok', 'done_with_errors' => 'warn', 'failed' => 'bad'][$last?->status] ?? ($last ? 'run' : 'line');
      @endphp
      <a href="{{ route('targets.show', $target) }}" class="card" style="color:var(--fg);display:flex;flex-direction:column;gap:12px;border-left:4px solid var(--{{ $tone }})">
        @if ($target->snapshot_path)
          <img class="snap" src="{{ asset('storage/'.$target->snapshot_path) }}" alt="Capture de {{ $target->name }}" loading="lazy">
        @else
          <div class="snap-empty">{{ $target->snapshot_error ? 'Capture impossible' : 'Capture en cours…' }}</div>
        @endif
        <div>
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
            <b>{{ $target->displayName() }}</b>
            @if ($last) @include('partials.status', ['status' => $last->status]) @endif
          </div>
          <div class="mono muted">{{ $target->url }}</div>
        </div>
        @if ($last && ! $last->isFinished())
          <div class="pbar"><div class="pfill running" style="width:100%"></div></div>
        @endif
        <div class="muted" style="font-size:13px">
          @if ($last)
            Dernière analyse : {{ $last->created_at->format('d/m/Y H:i') }} · {{ $last->summary['total_findings'] ?? 0 }} findings
          @else
            Aucune analyse
          @endif
        </div>
      </a>
    @endforeach
  </div>
@endif
@endsection
