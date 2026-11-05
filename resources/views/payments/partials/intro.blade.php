<x-integration-intro
    :logo="asset('imgs/others/logo-mercado-pago.png')"
    logo-alt="Logo Mercado Pago"
    title="Receba seus pagamentos diretamente no Mercado Pago"
    :text="$paymentAccount?->status?->value === \App\Enums\PaymentAccountsStatusEnum::NEEDS_REAUTH->value
        ? __('payments.account_needs_reauth')
        : 'Conecte sua conta e comece a cobrar seus pacientes de forma rápida, segura e automática.'"
    :url="route('settings.payment-account.connect')"
    button-label="Conectar com Mercado Pago"
/>
