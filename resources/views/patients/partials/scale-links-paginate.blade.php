{{-- Links de escala enviados ao paciente (paginação própria: .js-page-link). --}}
@if($links->isNotEmpty())
    <div class="pat-scale-links">
        @foreach($links as $link)
            @php($status = $link->status())
            <div class="pat-scale-link-row" data-scale-link="{{ $status }}">
                <div class="pat-scale-row-main">
                    <strong>{{ $link->scale->label() }}</strong>
                    <span class="pat-scale-date">Enviado em {{ formatDate($link->created_at, 'd/m/Y H:i', $timezone) }}</span>
                </div>
                <div class="pat-scale-row-result">
                    @switch($status)
                        @case('pending')
                            <span class="dash-chip dash-chip-warning">Aguardando · até {{ formatDate($link->expires_at, 'd/m/Y', $timezone) }}</span>
                            @break
                        @case('completed')
                            <span class="dash-chip dash-chip-success"><i class="fas fa-check"></i> Respondida em {{ formatDate($link->completed_at, 'd/m/Y', $timezone) }}</span>
                            @break
                        @case('revoked')
                            <span class="dash-chip dash-chip-muted">Cancelado</span>
                            @break
                        @default
                            <span class="dash-chip dash-chip-muted">Expirado</span>
                    @endswitch
                </div>
                @if($status === 'pending')
                    <div class="pat-scale-link-actions">
                        <button type="button" class="dash-round-btn" title="Copiar link" aria-label="Copiar link"
                                data-copy-scale-link="{{ route('public-scale.show', ['token' => $link->token]) }}">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                        <button type="button" class="dash-round-btn dash-round-muted" title="Cancelar link" aria-label="Cancelar link"
                                data-revoke-scale-link="{{ encrypt($link->id) }}" data-scale-name="{{ $link->scale->label() }}">
                            <i class="fas fa-ban"></i>
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if($links->hasPages())
        <nav class="pat-scale-link-pagination" aria-label="Paginação dos links">
            @if($links->onFirstPage())
                <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true"><i class="fas fa-chevron-left"></i></span>
            @else
                <a href="{{ $links->previousPageUrl() }}" class="btn btn-outline-secondary btn-sm js-page-link" rel="prev" title="Página anterior" aria-label="Página anterior"><i class="fas fa-chevron-left"></i></a>
            @endif

            <span class="pat-scale-link-page">Página {{ $links->currentPage() }} de {{ $links->lastPage() }}</span>

            @if($links->hasMorePages())
                <a href="{{ $links->nextPageUrl() }}" class="btn btn-outline-secondary btn-sm js-page-link" rel="next" title="Próxima página" aria-label="Próxima página"><i class="fas fa-chevron-right"></i></a>
            @else
                <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true"><i class="fas fa-chevron-right"></i></span>
            @endif
        </nav>
    @endif
@else
    <div class="dash-empty">
        <i class="fas fa-paper-plane"></i>Nenhum link enviado para este paciente ainda.
    </div>
@endif
