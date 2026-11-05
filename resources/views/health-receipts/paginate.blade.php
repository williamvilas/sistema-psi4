@if(!empty($receipts) && $receipts->isNotEmpty())

    @php($total = $receipts->total())
    <p class="dash-total mb-3">{{ trans_choice('messages.total_records', $total, ['count' => $total]) }}: {{ formatNumber($total) }}</p>

    <div class="row">
        @foreach($receipts as $receipt)
            @php($patient = $receipt->patient)
            @php($id = encrypt($receipt->id))
            @php($status = $receipt->status)
            @php($isPending = $status === \App\Enums\HealthReceiptStatusEnum::PENDING)
            @php($issues = $isPending ? $receipt->issues() : [])
            <div class="col-12 col-md-6 col-xl-4 col-xxl-3 mb-4">
                <div class="dash-item h-100">

                    <div class="dash-item-head">
                        @include('components.avatar-initials', ['name' => $patient->name])
                        <div class="min-w-0 flex-grow-1">
                            <span class="dash-item-name" title="{{ $patient->name }}">{{ $patient->name }}</span>
                            <div class="dash-item-sub text-truncate" title="{{ $receipt->description }}">{{ $receipt->description ?: __('health-receipts.default_description') }}</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between gap-2 mt-3">
                        <span class="hr-amount">{{ formatCurrency($receipt->amount) }}</span>
                        <span class="dash-chip dash-chip-{{ $status->tone() }} flex-shrink-0">{{ $status->getText() }}</span>
                    </div>

                    <div class="hr-meta">
                        <span><i class="fa-regular fa-calendar me-1"></i> Pago em {{ $receipt->paid_on->format('d/m/Y') }}</span>
                        <span>{{ $receipt->source->getText() }}</span>
                    </div>

                    @if($patient->payer_document && $isPending)
                        <p class="text-muted small mb-2"><i class="fas fa-user-group me-1"></i> Pago por {{ $patient->payer_name ?: 'responsável financeiro' }}</p>
                    @endif

                    @foreach($issues as $issue)
                        <p class="hr-issue mb-2"><i class="fas fa-triangle-exclamation"></i> {{ __('health-receipts.issues.'.$issue) }}</p>
                    @endforeach

                    @if($receipt->refunded_at)
                        <p class="hr-issue mb-2"><i class="fas fa-undo"></i> Pagamento estornado</p>
                    @endif

                    <div class="dash-item-actions">
                        @if(!empty($issues) && !$patient->trashed())
                            <a href="{{ route('patients.edit', ['id' => encrypt($patient->id)]) }}" class="btn dash-btn-soft"><i class="fas fa-pencil-alt me-1"></i> Corrigir paciente</a>
                        @endif

                        <div class="ms-auto d-flex gap-2">
                            <div class="dropdown">
                                <button type="button" class="dash-round-btn dash-round-muted" data-bs-toggle="dropdown" aria-expanded="false" title="Mais ações">
                                    <i class="fas fa-ellipsis-vertical icon-me-0 icon-refresh size-icon-ac"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @if($receipt->isEditable())
                                        <li>
                                            <button type="button" class="dropdown-item" onclick="openUpdateHealthReceipt(this)"
                                                    data-id="{{ $id }}"
                                                    data-patient-id="{{ encrypt($patient->id) }}"
                                                    data-patient-name="{{ $patient->name }}"
                                                    data-amount="{{ formatCurrency($receipt->amount) }}"
                                                    data-paid-on="{{ $receipt->paid_on->format('d/m/Y') }}"
                                                    data-description="{{ $receipt->description }}">
                                                <i class="fas fa-pencil-alt me-2"></i> Editar
                                            </button>
                                        </li>
                                    @endif
                                    @if($isPending)
                                        <li><button type="button" class="dropdown-item" onclick="skipHealthReceipt(@js($id), @js($patient->name))"><i class="fas fa-ban me-2"></i> Não emitir</button></li>
                                    @elseif($status === \App\Enums\HealthReceiptStatusEnum::CANCELLED && !$receipt->refunded_at)
                                        <li><button type="button" class="dropdown-item" onclick="restoreHealthReceipt(@js($id))"><i class="fas fa-rotate-left me-2"></i> Voltar a emitir</button></li>
                                    @elseif($status === \App\Enums\HealthReceiptStatusEnum::EXPORTED)
                                        <li><span class="dropdown-item-text text-muted small">Para tirar do arquivo, use "Desfazer" em Arquivos gerados.</span></li>
                                    @endif
                                    @if($receipt->isEditable())
                                        <li><hr class="dropdown-divider"></li>
                                        <li><button type="button" class="dropdown-item text-danger" onclick="deleteHealthReceipt(@js($id), @js($patient->name))"><i class="far fa-trash-alt me-2"></i> Apagar</button></li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        @endforeach

        <div class="col-12 d-flex justify-content-center">
            {!! $receipts->links() !!}
        </div>
    </div>

@else
    @include('components.empty', [
        'icon' => 'fas fa-file-invoice',
        'message' => (request()->filled('status') || request()->filled('patient_id') || request()->filled('start_date') || request()->filled('end_date'))
            ? 'Nenhum recibo encontrado para este filtro.'
            : 'Ainda não há recibos. As cobranças pagas pelo PSI4 aparecem aqui sozinhas; os pagamentos por fora você registra em "Registrar recebimento".',
    ])
@endif
