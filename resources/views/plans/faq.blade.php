@php
    // Perguntas frequentes da tela de planos. Valores do plano anual vêm de config('plans.plans') para não divergirem do checkout.
    $annual = \App\Helpers\PlanHelper::annualConfig('professional');
    $free = config('plans.plans.free');
    $mercadoPagoFees = collect(\App\Helpers\MercadoPagoFeeHelper::rows())
        ->map(fn (array $fee) => $fee['label'].' ('.$fee['term'].'): <strong>'.$fee['percent'].'</strong>')
        ->implode('<br>');

    $faq = [
        [
            'question' => 'Como funciona o plano Grátis?',
            'answer' => 'É grátis para sempre, sem cartão, com a mesma criptografia de ponta a ponta do Profissional. <strong>Pacientes, agenda, prontuários, escalas, vídeochamadas, relatórios e cobranças são ilimitados</strong>, e você gera os <strong>recibos do Receita Saúde em lote</strong>. A emissão de documentos e os avisos automáticos por WhatsApp ficam no Profissional. Nas cobranças pagas pelo Mercado Pago, o PSI4 cobra '.$free['payment_fee_percent'].'% sobre cada valor recebido, além da taxa do próprio Mercado Pago. No Profissional, o PSI4 não cobra taxa: fica só a do Mercado Pago.',
        ],
        [
            'question' => 'Quais são as taxas do Mercado Pago nas cobranças?',
            'answer' => $mercadoPagoFees.'<br><br>'.__('payments.fees.patient_interest', ['percent' => \App\Helpers\MercadoPagoFeeHelper::installmentsPatientMax()]).' '.__('payments.fees.note', ['date' => \App\Helpers\MercadoPagoFeeHelper::checkedAt()]),
        ],
        [
            'question' => 'Posso cancelar quando quiser?',
            'answer' => 'Sim, sem multa. No plano mensal, clique em <strong>Cancelar renovação</strong> aqui nesta tela. Você continua com acesso ao seu plano até o fim do período já pago e depois sua conta volta para o plano Grátis, com todos os seus registros.',
        ],
        [
            'question' => 'Como funciona o plano anual?',
            'answer' => $annual
                ? 'É um pagamento único com <strong>'.\App\Helpers\PlanHelper::annualDiscount('professional').'% de desconto</strong> sobre o valor mensal, com fidelidade de 12 meses: em até '.\App\Helpers\PlanHelper::annualInstallments('professional').'x sem juros no cartão ou à vista no PIX, com '.(int) ($annual['pix_discount'] ?? 0).'% de desconto adicional. Ele não renova sozinho: perto do vencimento, avisamos por e-mail e liberamos a renovação nesta tela.'
                : null,
        ],
        [
            'question' => 'Quais formas de pagamento são aceitas?',
            'answer' => 'Cartão de crédito (planos mensal e anual) e PIX (plano anual à vista). O pagamento é processado pelo <strong>Mercado Pago</strong> e os dados do seu cartão não ficam armazenados no PSI4.',
        ],
        [
            'question' => 'O que acontece com meus dados quando o Profissional acaba?',
            'answer' => 'Nada é apagado. Sua conta volta para o plano Grátis: seus pacientes, prontuários e documentos continuam salvos, cifrados e editáveis. O que você criar a partir daí segue as regras do Grátis: sem emissão de documentos nem avisos automáticos por WhatsApp, e com a taxa de '.$free['payment_fee_percent'].'% nas cobranças.',
        ],
        [
            'question' => 'Posso pedir reembolso?',
            'answer' => 'Sim. Se você se arrepender, pode pedir o reembolso em até <strong>7 dias</strong> após a contratação, conforme o Código de Defesa do Consumidor. É só falar com a nossa equipe pela <a href="'.route('help.index').'">Ajuda</a>.',
        ],
    ];
@endphp

<section class="dash-card plans-faq" aria-labelledby="plans-faq-title">
    <div class="dash-title" id="plans-faq-title"><i class="far fa-question-circle" aria-hidden="true"></i> Perguntas frequentes</div>

    <div class="accordion" id="plans-faq-list">
        @foreach(array_filter($faq, fn (?array $item) => $item !== null && $item['answer'] !== null) as $i => $item)
            <div class="accordion-item" data-plans-faq>
                <h2 class="accordion-header" id="plans-faq-heading-{{ $i }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#plans-faq-answer-{{ $i }}" aria-expanded="false" aria-controls="plans-faq-answer-{{ $i }}">
                        {{ $item['question'] }}
                    </button>
                </h2>
                <div id="plans-faq-answer-{{ $i }}" class="accordion-collapse collapse" aria-labelledby="plans-faq-heading-{{ $i }}" data-bs-parent="#plans-faq-list">
                    <div class="accordion-body">{!! $item['answer'] !!}</div>
                </div>
            </div>
        @endforeach
    </div>
</section>
