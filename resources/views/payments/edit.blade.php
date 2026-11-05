@extends('layouts.app')
@section('title', 'Editar cobrança')

@section('header')
    @include('components.css-dash')
    @include('payments.partials.form-css')
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-payments', ['css' => 'me-2']) Editar cobrança
        </h3>
    </div>

    <div class="row justify-content-center pay-form-page">
        <div class="col-12 col-xxl-10">

            @if(!empty($order))

                @php
                    $isAccountIntegrated = $paymentAccount?->status?->value === \App\Enums\PaymentAccountsStatusEnum::INTEGRATED->value;
                    // Mesmo critério do service: só edita pendente que não está com o checkout em criação
                    $isEditable = $order->isEditable();
                    $isIntegrated = $order->integration_status === \App\Enums\PaymentOrderIntegrationStatusEnum::INTEGRATED;
                    $id = encrypt($order->id);
                @endphp

                <a href="{{ route('payments.index') }}" class="link-default ms-2">
                    <i class="fas fa-arrow-left me-1"></i>
                    Voltar
                </a>

                @if($isEditable)
                    @include('payments.partials.account-warning', ['paymentAccount' => $paymentAccount])

                    @if($isIntegrated)
                        <div class="alert alert-info d-flex align-items-center mt-2">
                            <i class="fas fa-circle-info me-2"></i>
                            <span>{{ __('payments.integrated_edit_notice') }}</span>
                        </div>
                    @endif

                    <form id="form-update-payment-order" class="row g-4 mt-0 pay-form" autocomplete="off">
                        <input type="hidden" value="{{ $id }}" id="inputId"/>

                        <div class="col-12 col-lg-7">
                            @include('payments.partials.form', ['order' => $order, 'disabled' => ! $isAccountIntegrated])
                        </div>

                        <div class="col-12 col-lg-5">
                            @include('payments.partials.preview', [
                                'order' => $order,
                                'disabled' => ! $isAccountIntegrated,
                                'action' => 'updatePaymentOrder()',
                                'buttonLabel' => 'Salvar alterações',
                            ])
                        </div>
                    </form>
                @else
                    @php($status = $order->getCurrentStatus())
                    <div class="row justify-content-center">
                        <div class="col-12 col-lg-7 col-xl-6">
                            <div class="dash-card pay-form-card mt-2" data-payment-readonly>
                                <div class="alert alert-info d-flex align-items-center">
                                    <i class="fas fa-circle-info me-2"></i>
                                    <span>{{ __('payments.cannot_edit') }}</span>
                                </div>

                                <div class="pay-patient mb-3">
                                    @include('components.avatar-initials', ['name' => $order->patient->name])
                                    <div class="pay-patient-body">
                                        <div class="pay-patient-name" title="{{ $order->patient->name }}">{{ $order->patient->name }}</div>
                                        <div class="pay-patient-sub">{{ formatPhone($order->patient->phone) }}</div>
                                    </div>
                                    <span class="badge {{ $status->color() }}">{{ $status->getText() }}</span>
                                </div>

                                <div class="pay-readonly-row"><span>Valor</span><span>R$ {{ number_format((float) $order->amount, 2, ',', '.') }}</span></div>
                                <div class="pay-readonly-row"><span>Vencimento</span><span>{{ $order->due_date?->format('d/m/Y') ?? 'Sem vencimento' }}</span></div>
                                <div class="pay-readonly-row">
                                    <span>Formas de pagamento</span>
                                    <span>{{ collect($order->payment_methods ?? [])->map(fn ($method) => \App\Enums\PaymentMethodEnum::tryFrom($method)?->getText())->filter()->implode(' · ') }}</span>
                                </div>
                                @if($order->description)
                                    <div class="pay-readonly-row"><span>Descrição</span><span>{{ $order->description }}</span></div>
                                @endif

                                <a href="{{ route('payments.show', ['id' => $id]) }}" class="btn btn-primary w-100 mt-3">
                                    Ver detalhes da cobrança
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                @include('components.unauthorized')
                <p class="text-center sub-alert-p">Essa restrição garante a confidencialidade e a segurança das informações.</p>
            @endif
        </div>
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            initPaymentOrderForm('#form-update-payment-order');
        });
    </script>
@endsection
