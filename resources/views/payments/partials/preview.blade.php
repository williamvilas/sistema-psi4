@php
    // Resumo ao vivo: valores iniciais renderizados aqui e atualizados por updatePaymentOrderPreview() (payments.js).
    $order ??= null;
    $patient ??= null;
    $disabled ??= false;
    $patientName = $order?->patient?->name ?? $patient?->name;
@endphp

<aside class="dash-card pay-preview" aria-label="Resumo da cobrança">
    <span class="dash-eyebrow">Resumo da cobrança</span>

    <div class="pay-preview-patient {{ $patientName ? '' : 'is-empty' }}" data-preview="patient-wrap">
        <i class="far fa-user"></i>
        <span data-preview="patient">{{ $patientName ?? 'Selecione o paciente' }}</span>
    </div>

    <div class="pay-preview-amount is-empty" data-preview="amount">R$ 0,00</div>

    <ul class="pay-preview-list">
        <li><i class="far fa-calendar"></i><span data-preview="due">Sem vencimento</span></li>
        <li><i class="far fa-credit-card"></i><span data-preview="methods">—</span></li>
        <li data-preview="description-wrap" hidden><i class="far fa-comment"></i><span data-preview="description"></span></li>
    </ul>

    <p class="pay-preview-note">
        <i class="fas fa-lock"></i>
        <span>Depois de salvar, você copia o link seguro do Mercado Pago ou envia pelo WhatsApp para o paciente.</span>
    </p>

    <div class="d-none d-lg-block">
        <button type="button" class="btn btn-primary w-100" onclick="{{ $action }}" @disabled($disabled)>
            {{ $buttonLabel }}
        </button>
        <a href="{{ route('payments.index') }}" class="btn btn-link w-100 mt-1">Cancelar</a>
    </div>
</aside>

<div class="pay-mobile-bar d-lg-none">
    <span class="pay-mobile-bar-amount" data-preview="amount">R$ 0,00</span>
    <button type="button" class="btn btn-primary" onclick="{{ $action }}" @disabled($disabled)>{{ $buttonLabel }}</button>
</div>
