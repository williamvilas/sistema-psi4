@extends('layouts.app')
@section('title', 'Detalhes da cobrança')

@section('header')
    @include('components.css-dash')
    @include('payments.partials.show-css')
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-payments', ['css' => 'me-2']) Detalhes da cobrança
        </h3>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">

            @if(!empty($order))

                @php
                    $patient = $order->patient;
                    $id = encrypt($order->id);
                    $patientId = encrypt($patient->id);
                    $timezone = \App\Helpers\UserHelper::getTimezone();
                    $status = $order->getCurrentStatus();
                    $isPending = $status === \App\Enums\PaymentOrderStatusEnum::PENDING;
                    $linkFailed = $isPending && ! $order->checkout_url && $order->integration_status === \App\Enums\PaymentOrderIntegrationStatusEnum::ERROR;
                    $isGeneratingLink = $isPending && ! $order->checkout_url && ! $linkFailed;
                    $paidAt = $order->paid_at?->timezone($timezone);
                    $createdAt = $order->created_at->timezone($timezone);
                    $dueDate = $order->due_date?->format('d/m/Y');
                    $methodsAccepted = collect($order->payment_methods ?? [])->map(fn ($method) => \App\Enums\PaymentMethodEnum::tryFrom($method))->filter();
                    $usedMethod = $order->payment_method ? ' via '.$order->payment_method->getText() : '';
                @endphp

                <a href="{{ route('payments.index') }}" class="link-default ms-2">
                    <i class="fas fa-arrow-left me-1"></i>
                    Voltar
                </a>

                <div class="row g-4 mt-0">
                    <div class="col-12 col-lg-8">

                        <section class="dash-card pay-show-card" data-payment-show>
                            <div class="pay-show-head">
                                <span class="dash-eyebrow">Cobrança</span>
                                <span class="dash-chip dash-chip-{{ $status->tone() }}" data-payment-status>{{ $status->getText() }}</span>
                            </div>

                            <div class="pay-show-amount">{{ formatCurrency($order->amount) }}</div>

                            <div class="pay-show-meta">
                                @include('payments.partials.due-date', ['order' => $order, 'status' => $status, 'timezone' => $timezone])
                                @if($order->description)
                                    <span class="pay-show-meta-item"><i class="far fa-comment me-2"></i>{{ $order->description }}</span>
                                @endif
                            </div>

                            @if($isPending && $order->checkout_url)
                                <div class="pay-show-state" data-payment-link>
                                    <div class="pay-show-state-title"><i class="fas fa-link text-primary"></i> Link de pagamento</div>
                                    <p class="pay-show-state-text">Envie para o paciente pagar com segurança pelo Mercado Pago.</p>
                                    <a href="{{ $order->checkout_url }}" target="_blank" rel="noopener" class="pay-show-link-url" title="{{ $order->checkout_url }}">{{ $order->checkout_url }}</a>
                                    <div class="pay-show-link-actions">
                                        <button type="button" class="btn btn-primary" onclick="copyPaymentLink(@js($order->checkout_url))">
                                            <i class="far fa-copy me-2"></i> Copiar link
                                        </button>
                                        @if($patient->phone)
                                            @include('payments.partials.whatsapp-button', ['order' => $order, 'patient' => $patient, 'class' => 'btn pay-show-whatsapp', 'label' => 'Enviar pelo WhatsApp'])
                                        @endif
                                        <a href="{{ $order->checkout_url }}" target="_blank" rel="noopener" class="btn btn-link">
                                            Abrir <i class="fas fa-arrow-up-right-from-square ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            @elseif($isGeneratingLink)
                                <div class="pay-show-state" data-payment-generating>
                                    <div class="pay-show-state-title">
                                        <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span> Gerando link de pagamento...
                                    </div>
                                    <p class="pay-show-state-text" data-payment-generating-text>Isso leva poucos segundos. A página será atualizada automaticamente.</p>
                                </div>
                            @elseif($linkFailed)
                                <div class="pay-show-state is-danger">
                                    <div class="pay-show-state-title"><i class="fas fa-triangle-exclamation"></i> Não foi possível gerar o link</div>
                                    <p class="pay-show-state-text">O Mercado Pago não gerou o link desta cobrança. Apague-a e crie uma nova cobrança para o paciente.</p>
                                </div>
                            @else
                                @php
                                    [$stateTone, $stateIcon, $stateTitle, $stateText] = match ($status) {
                                        \App\Enums\PaymentOrderStatusEnum::PAID => ['is-success', 'fa-circle-check', 'Pagamento recebido',
                                            ($paidAt ? 'Pago em '.$paidAt->format('d/m/Y').' às '.$paidAt->format('H:i') : 'Pagamento confirmado').$usedMethod.'.'],
                                        \App\Enums\PaymentOrderStatusEnum::PROCESSING => ['is-info', 'fa-hourglass-half', 'Pagamento em processamento', 'O Mercado Pago está confirmando o pagamento. O status será atualizado automaticamente.'],
                                        \App\Enums\PaymentOrderStatusEnum::REVERSING => ['is-info', 'fa-rotate-left', 'Estorno em andamento', 'O Mercado Pago está devolvendo o valor ao pagador.'],
                                        \App\Enums\PaymentOrderStatusEnum::REFUNDED => ['is-info', 'fa-rotate-left', 'Pagamento estornado', 'O valor foi devolvido ao pagador pelo Mercado Pago.'],
                                        \App\Enums\PaymentOrderStatusEnum::REJECTED => ['is-danger', 'fa-circle-xmark', 'Pagamento recusado', 'O Mercado Pago recusou o pagamento desta cobrança.'],
                                        \App\Enums\PaymentOrderStatusEnum::EXPIRED => ['is-warning', 'fa-calendar-xmark', 'Cobrança vencida', 'Venceu em '.$dueDate.' e o link não está mais disponível para o paciente.'],
                                        default => ['', 'fa-ban', 'Cobrança cancelada', 'Esta cobrança não pode mais ser paga.'],
                                    };
                                @endphp
                                <div class="pay-show-state {{ $stateTone }}">
                                    <div class="pay-show-state-title"><i class="fas {{ $stateIcon }}"></i> {{ $stateTitle }}</div>
                                    <p class="pay-show-state-text">{{ $stateText }}</p>
                                </div>
                            @endif
                        </section>

                        <section class="dash-card pay-show-card mt-4">
                            <div class="pay-show-section">
                                <span class="dash-eyebrow">Detalhes</span>
                                <div class="mt-2">
                                    <div class="pay-show-row">
                                        <span>Formas aceitas</span>
                                        <span class="pay-show-methods">
                                            @foreach($methodsAccepted as $method)
                                                <span><i class="{{ $method->icon() }} me-1"></i>{{ $method->getText() }}</span>
                                            @endforeach
                                        </span>
                                    </div>
                                    <div class="pay-show-row">
                                        <span>Forma utilizada</span>
                                        @if($order->payment_method)
                                            <span><i class="{{ $order->payment_method->icon() }} me-1"></i>{{ $order->payment_method->getText() }}</span>
                                        @else
                                            <span class="pay-show-empty">Ainda não paga</span>
                                        @endif
                                    </div>
                                    <div class="pay-show-row">
                                        <span>Vencimento</span>
                                        <span class="{{ $dueDate ? '' : 'pay-show-empty' }}">{{ $dueDate ?? 'Sem vencimento' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pay-show-section">
                                <span class="dash-eyebrow">Histórico</span>
                                <ul class="pay-timeline">
                                    <li class="is-done">
                                        <span class="pay-timeline-dot"></span>
                                        <div>
                                            <span class="pay-timeline-title">Cobrança criada</span>
                                            <span class="pay-timeline-date">{{ $createdAt->format('d/m/Y') }} às {{ $createdAt->format('H:i') }}</span>
                                        </div>
                                    </li>
                                    <li class="{{ $order->checkout_url ? 'is-done' : ($linkFailed ? 'is-danger' : 'is-next') }}">
                                        <span class="pay-timeline-dot"></span>
                                        <div>
                                            <span class="pay-timeline-title">{{ $order->checkout_url ? 'Link de pagamento gerado' : ($linkFailed ? 'Falha ao gerar o link' : 'Gerando link de pagamento') }}</span>
                                        </div>
                                    </li>
                                    @if($paidAt)
                                        <li class="is-success">
                                            <span class="pay-timeline-dot"></span>
                                            <div>
                                                <span class="pay-timeline-title">Pagamento recebido{{ $usedMethod }}</span>
                                                <span class="pay-timeline-date">{{ $paidAt->format('d/m/Y') }} às {{ $paidAt->format('H:i') }}</span>
                                            </div>
                                        </li>
                                    @endif
                                    @if(in_array($status, [\App\Enums\PaymentOrderStatusEnum::REVERSING, \App\Enums\PaymentOrderStatusEnum::REFUNDED], true))
                                        <li class="{{ $status === \App\Enums\PaymentOrderStatusEnum::REFUNDED ? 'is-done' : 'is-next' }}">
                                            <span class="pay-timeline-dot"></span>
                                            <div><span class="pay-timeline-title">{{ $status === \App\Enums\PaymentOrderStatusEnum::REFUNDED ? 'Pagamento estornado' : 'Estorno em andamento' }}</span></div>
                                        </li>
                                    @elseif($status === \App\Enums\PaymentOrderStatusEnum::EXPIRED)
                                        <li class="is-danger">
                                            <span class="pay-timeline-dot"></span>
                                            <div>
                                                <span class="pay-timeline-title">Cobrança vencida</span>
                                                <span class="pay-timeline-date">{{ $dueDate }}</span>
                                            </div>
                                        </li>
                                    @elseif(in_array($status, [\App\Enums\PaymentOrderStatusEnum::REJECTED, \App\Enums\PaymentOrderStatusEnum::CANCELLED], true))
                                        <li class="is-danger">
                                            <span class="pay-timeline-dot"></span>
                                            <div><span class="pay-timeline-title">{{ $status === \App\Enums\PaymentOrderStatusEnum::REJECTED ? 'Pagamento recusado' : 'Cobrança cancelada' }}</span></div>
                                        </li>
                                    @elseif(! $paidAt && ! $linkFailed)
                                        <li class="is-next">
                                            <span class="pay-timeline-dot"></span>
                                            <div><span class="pay-timeline-title">Aguardando pagamento</span></div>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </section>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="pay-show-aside">
                            <section class="dash-card pay-show-card">
                                <span class="dash-eyebrow">Paciente</span>
                                <div class="pay-show-patient">
                                    @include('components.avatar-initials', ['name' => $patient->name])
                                    <div class="pay-show-patient-body">
                                        <span class="pay-show-patient-name" title="{{ $patient->name }}">{{ $patient->name }}</span>
                                        @if(! $patient->trashed())
                                            <a href="{{ route('patients.show', ['id' => $patientId]) }}" class="dash-more mt-0">Ver ficha <i class="fas fa-arrow-right ms-1"></i></a>
                                        @else
                                            <small class="text-muted">Paciente removido</small>
                                        @endif
                                    </div>
                                </div>

                                <ul class="pay-show-contacts">
                                    <li>
                                        <i class="fab fa-whatsapp"></i>
                                        @if($patient->phone)
                                            <a href="{{ route('whatsapp.send', ['phone' => $patient->phone]) }}" target="_blank" rel="noopener" title="Abrir conversa no WhatsApp">{{ formatPhone($patient->phone) }}</a>
                                        @else
                                            <span class="pay-show-empty">WhatsApp não informado</span>
                                        @endif
                                    </li>
                                    <li>
                                        <i class="far fa-envelope"></i>
                                        @if($patient->email)
                                            <a href="mailto:{{ $patient->email }}">{{ $patient->email }}</a>
                                        @else
                                            <span class="pay-show-empty">E-mail não informado</span>
                                        @endif
                                    </li>
                                    <li>
                                        <i class="far fa-id-card"></i>
                                        @if($patient->document)
                                            <span>{{ formatCPF($patient->document) }}</span>
                                        @else
                                            <span class="pay-show-empty">CPF não informado</span>
                                        @endif
                                    </li>
                                </ul>
                            </section>

                            <section class="dash-card pay-show-card mt-4">
                                <span class="dash-eyebrow">Ações</span>
                                <div class="pay-show-actions">
                                    @if($order->isEditable())
                                        <a href="{{ route('payments.edit', ['id' => $id]) }}" class="btn btn-outline-primary">
                                            <i class="fas fa-pencil-alt me-2"></i> Editar cobrança
                                        </a>
                                    @endif
                                    @if($order->status === \App\Enums\PaymentOrderStatusEnum::PAID)
                                        <button type="button" class="btn btn-outline-primary" onclick="refundPaymentOrder(@js($id), @js($patient->name), true)">
                                            <i class="fas fa-undo me-2"></i> Estornar pagamento
                                        </button>
                                    @endif
                                    @if(! $patient->trashed())
                                        <a href="{{ route('payments.create', ['patient' => $patientId]) }}" class="btn btn-outline-primary">
                                            <i class="fas fa-plus me-2"></i> Nova cobrança para o paciente
                                        </a>
                                    @endif
                                    <button type="button" class="btn pay-show-delete" onclick="deletePaymentOrders(@js($id), @js($patient->name), true)">
                                        <i class="far fa-trash-alt me-2"></i> Apagar cobrança
                                    </button>
                                </div>
                                <p class="text-muted small mt-3 mb-0">Criada em {{ $createdAt->format('d/m/Y') }} às {{ $createdAt->format('H:i') }}</p>
                            </section>
                        </div>
                    </div>
                </div>
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
            watchPaymentLink('[data-payment-generating]');
        });
    </script>
@endsection
