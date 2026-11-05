@php
    // Botão "Enviar link pelo WhatsApp" com a mensagem da cobrança pronta (listagem e detalhes).
    $label ??= null;
    $class ??= 'dash-round-btn dash-round-btn-success';
    $whatsappUrl = route('whatsapp.send', ['phone' => $patient->phone, 'text' => __('payments.whatsapp_message', [
        'name' => \Illuminate\Support\Str::of($patient->name)->explode(' ')->first(),
        'amount' => formatCurrency($order->amount),
        'link' => $order->checkout_url,
    ])]);
@endphp
<a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="{{ $class }}" title="Enviar link pelo WhatsApp">
    <i class="fab fa-whatsapp"></i>@if($label)<span class="ms-2">{{ $label }}</span>@endif
</a>
