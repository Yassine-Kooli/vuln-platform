@if ($paginator->hasPages())
  <nav class="pager" aria-label="Pages">
    @if ($paginator->onFirstPage())
      <span class="btn" style="opacity:.4">‹ Précédent</span>
    @else
      <a class="btn" href="{{ $paginator->previousPageUrl() }}">‹ Précédent</a>
    @endif
    <span class="muted">Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())
      <a class="btn" href="{{ $paginator->nextPageUrl() }}">Suivant ›</a>
    @else
      <span class="btn" style="opacity:.4">Suivant ›</span>
    @endif
  </nav>
@endif
