@extends('layout')
@section('title', $target->displayName())
@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
  <div>
    <h1>{{ $target->displayName() }}</h1>
    <div class="mono muted">{{ $target->url }}</div>
  </div>
  <div style="display:flex;gap:8px">
    <form class="inline" method="POST" action="{{ route('targets.snapshot', $target) }}">@csrf<button class="btn" type="submit">Recapturer</button></form>
    <form class="inline" method="POST" action="{{ route('targets.destroy', $target) }}" onsubmit="return confirm('Supprimer cette cible ? Les rapports déjà archivés dans results/ restent.')">
      @csrf @method('DELETE')<button class="btn danger" type="submit">Supprimer</button></form>
  </div>
</div>

<div class="grid g-two" style="margin-top:20px">
  <div class="card">
    <h2>Capture de la page</h2>
    @if ($target->snapshot_path)
      <img class="snap" src="{{ asset('storage/'.$target->snapshot_path) }}" alt="Capture de {{ $target->name }}">
      <div class="hint">Capturée le {{ $target->snapshot_at?->format('d/m/Y H:i') }}</div>
    @else
      <div class="snap-empty">{{ $target->snapshot_error ? 'Capture impossible : '.$target->snapshot_error : 'Capture en cours… si rien n\'apparaît après une minute, relancez ./start.sh.' }}</div>
    @endif
  </div>

  <div class="card">
    <h2>Lancer une analyse</h2>
    @if ($target->source_path)
      <div class="mono muted">Code : {{ $target->source_path }}</div>
    @else
      <div class="err">Aucun code source : les étapes SAST et SCA seront en échec.</div>
    @endif
    <form method="POST" action="{{ route('scans.store', $target) }}" style="margin-top:14px">
      @csrf
      <label class="f">Étapes</label>
      <div class="checks">
        @foreach (['sast' => 'SAST (Semgrep)', 'sca' => 'SCA (Trivy, npm audit)', 'dast' => 'DAST (ZAP)'] as $key => $text)
          <label><input type="checkbox" name="stages[]" value="{{ $key }}" checked> {{ $text }}</label>
        @endforeach
      </div>
      @error('stages')<div class="err">{{ $message }}</div>@enderror
      @php $busy = $scans->first(fn ($s) => in_array($s->status, ['queued', 'running'], true)); @endphp
      @if ($busy)
        <div class="notice">Une analyse est déjà en cours (#{{ $busy->id }}). <a href="{{ route('scans.show', $busy) }}">Voir l'avancement</a></div>
      @endif
      <div style="margin-top:16px"><button class="btn primary" type="submit" {{ $busy ? 'disabled' : '' }}>Lancer l'analyse</button></div>
    </form>
    <div class="hint">Une analyse à la fois : les suivantes attendent dans la file.</div>
  </div>
</div>

<h2 style="margin-top:28px">Historique des analyses</h2>
<div class="card" style="padding:0;overflow:auto">
  <table>
    <thead><tr><th>Date</th><th>Étapes</th><th>Statut</th><th class="num">Findings</th><th></th></tr></thead>
    <tbody>
    @forelse ($scans as $scan)
      <tr>
        <td>{{ $scan->created_at->format('d/m/Y H:i') }}</td>
        <td>{{ strtoupper(implode(' + ', $scan->stages)) }}</td>
        <td>@include('partials.status', ['status' => $scan->status])</td>
        <td class="num">{{ $scan->findings_count }}</td>
        <td><a href="{{ route('scans.show', $scan) }}">Ouvrir</a></td>
      </tr>
    @empty
      <tr><td colspan="5" class="empty">Aucune analyse pour cette cible.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
