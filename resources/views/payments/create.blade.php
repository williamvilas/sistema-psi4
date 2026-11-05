@extends('layouts.app')
@section('title', 'Nova cobrança')

@section('header')
    @include('components.css-dash')
    @include('payments.partials.form-css')
@endsection

@section('content')

    @php
        $isAccountIntegrated = $paymentAccount?->status?->value === \App\Enums\PaymentAccountsStatusEnum::INTEGRATED->value;
    @endphp

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-payments', ['css' => 'me-2']) Nova cobrança
        </h3>
    </div>

    <div class="row justify-content-center pay-form-page">
        <div class="col-12 col-xxl-10">
            <a href="{{ route('payments.index') }}" class="link-default ms-2">
                <i class="fas fa-arrow-left me-1"></i>
                Voltar
            </a>

            @include('payments.partials.account-warning', ['paymentAccount' => $paymentAccount])

            <form id="form-store-payment-order" class="row g-4 mt-0 pay-form" autocomplete="off">
                <div class="col-12 col-lg-7">
                    @include('payments.partials.form', ['patient' => $patient, 'disabled' => ! $isAccountIntegrated])
                </div>

                <div class="col-12 col-lg-5">
                    @include('payments.partials.preview', [
                        'patient' => $patient,
                        'disabled' => ! $isAccountIntegrated,
                        'action' => 'storePaymentOrder()',
                        'buttonLabel' => 'Criar cobrança',
                    ])
                </div>
            </form>
        </div>
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            initPaymentOrderForm('#form-store-payment-order');
        });
    </script>
@endsection
