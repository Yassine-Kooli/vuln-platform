@php
  $labels = ['queued' => 'En file', 'running' => 'En cours', 'done' => 'Terminé', 'done_with_errors' => 'Terminé avec erreurs', 'failed' => 'Échec'];
@endphp
<span class="pill {{ $status }}">{{ $labels[$status] ?? $status }}</span>
