@if(!empty($docs) && $docs->isNotEmpty())

    @php($total = $docs->total())
    <p class="dash-total mb-3">{{ trans_choice('messages.total_records', $total, ['count' => $total]) }}: {{ formatNumber($total) }}</p>

    <div class="row">
        @foreach($docs as $doc)
            @php($id = encrypt($doc->id))
            @php($typeText = __('documents.'.$doc->type->value))
            @php($icon = $doc->type->icon())
            <div class="col-12 col-md-6 col-xl-4 col-xxl-3 mb-4">
                <div class="dash-item doc-card h-100 position-relative" data-document-card data-document-type="{{ $typeText }}">

                    <div class="dash-item-head">
                        <div class="doc-card-icon dash-tone-{{ $doc->type->tone() }}">
                            <i class="{{ $icon }}"></i>
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <span class="dash-item-name" title="{{ $typeText }}">{{ $typeText }}</span>
                            <div class="dash-item-sub text-truncate" title="{{ $doc->title }}">{{ $doc->title }}</div>
                        </div>
                    </div>

                    <p class="doc-card-desc">{{ $doc->type->description() }}</p>

                    @if($doc->type->hasAgenda())
                        <div class="doc-card-chips">
                            <span class="dash-chip dash-chip-info"><i class="far fa-calendar-alt"></i> Usa sessões realizadas</span>
                        </div>
                    @endif

                    <div class="dash-item-actions">
                        <button type="button" class="btn dash-btn-soft w-100 stretched-link" data-document-emit
                                onclick="previewDocument(@js($id), @js($typeText), @js($icon))">
                            <i class="fas fa-file-pdf me-2"></i> Emitir documento
                        </button>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-12 d-flex justify-content-center">
            {!! $docs->links() !!}
        </div>
    </div>

@else
    @include('components.empty', [
        'icon' => 'far fa-folder-open',
        'message' => ($hasFilters ?? false) ? 'Nenhum documento encontrado para este filtro.' : 'Nenhum modelo de documento disponível no momento.',
    ])
@endif
