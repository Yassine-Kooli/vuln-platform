@extends('layout')
@section('title', 'Ajouter une cible')
@section('content')
<h1>Ajouter une cible</h1>
<p class="sub">Indiquez l'application à tester et le code source correspondant. La plateforme capture la page automatiquement.</p>

<div class="card" style="max-width:720px">
<form method="POST" action="{{ route('targets.store') }}">
  @csrf
  <label class="f" for="name">Nom (identifiant)</label>
  <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="juice-shop" required>
  <div class="hint">Lettres minuscules, chiffres et tirets. Sert de nom de dossier et d'identifiant.</div>
  @error('name')<div class="err">{{ $message }}</div>@enderror

  <label class="f" for="label">Libellé (optionnel)</label>
  <input type="text" id="label" name="label" value="{{ old('label') }}" placeholder="OWASP Juice Shop">
  @error('label')<div class="err">{{ $message }}</div>@enderror

  <label class="f" for="url">URL de l'application (DAST et capture)</label>
  <input type="url" id="url" name="url" value="{{ old('url') }}" placeholder="http://localhost:3000" required>
  @error('url')<div class="err">{{ $message }}</div>@enderror

  <label class="f" for="source_path">Dossier du code source (SAST, SCA)</label>
  <input type="text" id="source_path" name="source_path" value="{{ old('source_path') }}" placeholder="/chemin/vers/le/code">
  <div class="hint">Dossier présent sur cette machine, lu sans être modifié.</div>
  @error('source_path')<div class="err">{{ $message }}</div>@enderror

  <label class="f" for="image">Image Docker (optionnel, SCA)</label>
  <input type="text" id="image" name="image" value="{{ old('image') }}" placeholder="bkimminich/juice-shop:latest">
  @error('image')<div class="err">{{ $message }}</div>@enderror

  <div style="margin-top:20px;display:flex;gap:10px">
    <button class="btn primary" type="submit">Ajouter la cible</button>
    <a class="btn" href="{{ route('dashboard') }}">Annuler</a>
  </div>
</form>
</div>
@endsection
