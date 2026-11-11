@php
  $sevs = ['critical', 'high', 'medium', 'low', 'info'];
  $max = max(1, ...array_values($counts ?: [0]));
@endphp
@foreach ($sevs as $s)
  <div class="bar"><span>{{ $s }}</span>
    <div class="t"><div class="f" style="width: {{ (($counts[$s] ?? 0) / $max) * 100 }}%; background: var(--{{ $s }})"></div></div>
    <span style="text-align:right">{{ $counts[$s] ?? 0 }}</span></div>
@endforeach
