@if(!empty($orders) && $orders->isNotEmpty())

    @php($total = $orders->total())
    <p class="dash-total mb-3">{{ trans_choice('messages.total_records', $total, ['count' => $total]) }}: {{ formatNumber($total) }}</p>

    <div class="row">
        @php($timezoneByUser = \App\Helpers\UserHelper::getTimezone())
        @foreach($orders as $order)
            @php($patient = $order->patient)
            @php($id = encrypt($order->id))
            @php($status = $order->getCurrentStatus())
            @php($isPending = $status === \App\Enums\PaymentOrderStatusEnum::PENDING)
            @php($showUrl = route('payments.show', ['id' => $id]))
            <div class="col-12 col-md-6 col-xl-4 col-xxl-3 mb-4">
                <div class="dash-item h-100">

                    <div class="dash-item-head">
                        @include('components.avatar-initials', ['name' => $patient->name])
                        <div class="min-w-0 flex-grow-1">
                            <a href="{{ $showUrl }}" class="dash-item-name" title="{{ $patient->name }}">{{ $patient->name }}</a>
                            <div class="dash-item-sub text-truncate" title="{{ $order->description }}">{{ $order->description ?: 'Criada em '.$order->created_at->timezone($timezoneByUser)->format('d/m/Y') }}</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between gap-2 mt-3">
                        <span class="pay-card-amount">{{ formatCurrency($order->amount) }}</span>
                        <span class="dash-chip dash-chip-{{ $status->tone() }} flex-shrink-0">{{ $status->getText() }}</span>
                    </div>

                    <div class="pay-card-meta">
                        @include('payments.partials.due-date', ['order' => $order, 'status' => $status, 'timezone' => $timezoneByUser])
                        <span class="pay-methods">
                            @foreach($order->payment_methods as $method)
                                @php($methodEnum = \App\Enums\PaymentMethodEnum::tryFrom($method))
                                @if($methodEnum)
                                    <i class="{{ $methodEnum->icon() }}" title="{{ $methodEnum->getText() }}" aria-label="{{ $methodEnum->getText() }}"></i>
                                @endif
                            @endforeach
                        </span>
                    </div>

                    <div class="dash-item-actions">
                        @if($isPending && $order->checkout_url)
                            <button type="button" class="btn dash-btn-soft" onclick="copyPaymentLink(@js($order->checkout_url))" title="Copiar link de pagamento">
                                <i class="far fa-copy me-1"></i> Copiar link
                            </button>
                            @if($patient->phone)
                                @include('payments.partials.whatsapp-button', ['order' => $order, 'patient' => $patient])
                            @endif
                        @elseif($isPending && $order->integration_status === \App\Enums\PaymentOrderIntegrationStatusEnum::ERROR)
                            <span class="pay-link-state text-danger"><i class="fas fa-triangle-exclamation me-1"></i> Falha ao gerar o link</span>
                        @elseif($isPending)
                            <span class="pay-link-state">
                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Gerando link...
                            </span>
                        @else
                            <a href="{{ $showUrl }}" class="btn dash-btn-soft"><i class="far fa-eye me-1"></i> Ver detalhes</a>
                        @endif

                        <div class="ms-auto d-flex gap-2">
                            @if($isPending)
                                <a href="{{ $showUrl }}" class="dash-round-btn dash-round-muted" title="Visualizar"><i class="far fa-eye icon-me-0 size-icon-ac"></i></a>
                            @endif
                            <div class="dropdown">
                                <button type="button" class="dash-round-btn dash-round-muted" data-bs-toggle="dropdown" aria-expanded="false" title="Mais ações">
                                    <i class="fas fa-ellipsis-vertical icon-me-0 icon-refresh size-icon-ac"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @if($order->isEditable())
                                        <li><a class="dropdown-item" href="{{ route('payments.edit', ['id' => $id]) }}"><i class="fas fa-pencil-alt me-2"></i> Editar</a></li>
                                    @endif
                                    @if($order->status === \App\Enums\PaymentOrderStatusEnum::PAID)
                                        <li><button type="button" class="dropdown-item" onclick="refundPaymentOrder(@js($id), @js($patient->name))"><i class="fas fa-undo me-2"></i> Estornar</button></li>
                                    @endif
                                    @if($order->isEditable() || $order->status === \App\Enums\PaymentOrderStatusEnum::PAID)
                                        <li><hr class="dropdown-divider"></li>
                                    @endif
                                    <li><button type="button" class="dropdown-item text-danger" onclick="deletePaymentOrders(@js($id), @js($patient->name))"><i class="far fa-trash-alt me-2"></i> Apagar</button></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        @endforeach

        <div class="col-12 d-flex justify-content-center">
            {!! $orders->links() !!}
        </div>
    </div>

@else
    @include('components.empty', [
        'icon' => 'fas fa-file-invoice-dollar',
        'message' => (request()->filled('status') || request()->filled('patient_id')) ? 'Nenhuma cobrança encontrada para este filtro.' : 'Você ainda não criou nenhuma cobrança.',
    ])
@endif
