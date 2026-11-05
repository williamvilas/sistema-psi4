@php
    $isAccountIntegrated = $paymentAccount?->status?->value === \App\Enums\PaymentAccountsStatusEnum::INTEGRATED->value;
@endphp

@unless ($isAccountIntegrated)
    <div class="alert alert-warning alert-dismissible mt-2 d-flex align-items-center" role="alert">
        <div class="icon me-2"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="message">
            <strong>Atenção!</strong>
            {{ $paymentAccount?->status?->value === \App\Enums\PaymentAccountsStatusEnum::NEEDS_REAUTH->value
                ? __('payments.account_needs_reauth')
                : __('payments.account_not_connected') }}
            <a href="{{ route('settings.payment-account.connect') }}" class="font-weight-bolder">
                Conectar com Mercado Pago <i class="fas fa-external-link-alt ms-1"></i>
            </a>
        </div>
    </div>
@endunless
