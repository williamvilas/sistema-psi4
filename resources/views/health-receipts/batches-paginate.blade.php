@if(!empty($batches) && $batches->isNotEmpty())

    <div class="row">
        @php($timezoneByUser = \App\Helpers\UserHelper::getTimezone())
        @foreach($batches as $batch)
            @php($id = encrypt($batch->id))
            @php($status = $batch->status)
            @php($isUndone = $status === \App\Enums\HealthReceiptBatchStatusEnum::UNDONE)
            <div class="col-12 col-md-6 col-xl-4 mb-4">
                <div class="dash-item h-100">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <span class="dash-item-name">Arquivo de {{ $batch->year }}</span>
                        <span class="dash-chip dash-chip-{{ $status->tone() }} flex-shrink-0">{{ $status->getText() }}</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between gap-2 mt-3">
                        <span class="hr-amount">{{ formatCurrency($batch->total_amount) }}</span>
                        <span class="text-muted">{{ $batch->receipts_count }} {{ $batch->receipts_count === 1 ? 'recibo' : 'recibos' }}</span>
                    </div>

                    <div class="hr-meta">
                        <span>Gerado em {{ $batch->created_at->timezone($timezoneByUser)->format('d/m/Y H:i') }}</span>
                        @if($batch->imported_at)
                            <span>Importado em {{ $batch->imported_at->timezone($timezoneByUser)->format('d/m/Y') }}</span>
                        @endif
                    </div>

                    @unless($isUndone)
                        <div class="dash-item-actions">
                            <button type="button" class="btn dash-btn-soft" onclick="downloadHealthReceiptBatch(@js(route('health-receipts.batches.download', ['id' => $id])), @js($status === \App\Enums\HealthReceiptBatchStatusEnum::IMPORTED))">
                                <i class="fas fa-download me-1"></i> Baixar
                            </button>

                            <div class="ms-auto d-flex gap-2">
                                <div class="dropdown">
                                    <button type="button" class="dash-round-btn dash-round-muted" data-bs-toggle="dropdown" aria-expanded="false" title="Mais ações">
                                        <i class="fas fa-ellipsis-vertical icon-me-0 icon-refresh size-icon-ac"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @if($status === \App\Enums\HealthReceiptBatchStatusEnum::GENERATED)
                                            <li><button type="button" class="dropdown-item" onclick="markHealthReceiptBatchImported(@js($id))"><i class="fas fa-check me-2"></i> Já importei no e-CAC</button></li>
                                        @endif
                                        <li><button type="button" class="dropdown-item text-danger" onclick="undoHealthReceiptBatch(@js($id), @js($status === \App\Enums\HealthReceiptBatchStatusEnum::IMPORTED))"><i class="fas fa-rotate-left me-2"></i> Desfazer</button></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endunless
                </div>
            </div>
        @endforeach

        <div class="col-12 d-flex justify-content-center">
            {!! $batches->links() !!}
        </div>
    </div>

@else
    @include('components.empty', [
        'icon' => 'fas fa-file-export',
        'message' => 'Você ainda não gerou nenhum arquivo para o Carnê-Leão.',
    ])
@endif
