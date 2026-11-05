<section id="precos" class="lp-section" aria-labelledby="plans-title">
    <div class="lp-container">
        <div class="lp-measure lp-center lp-reveal">
            <span class="lp-eyebrow">Preços</span>
            <h2 id="plans-title">Menos que uma sessão por mês.</h2>
            <p class="lp-lead">Comece sem pagar nada, no seu ritmo. Quando o consultório pedir mais, você migra para o plano {{ $proName }}.</p>
        </div>

        @if($annual)
            {{-- Só CSS (:has): o rádio marcado mostra o preço do ciclo no card do plano pago --}}
            <fieldset class="lp-cycle lp-reveal">
                <legend>Selecione a periodicidade</legend>
                <div class="lp-cycle-options">
                    <input type="radio" class="lp-cycle-input" name="plan-cycle" id="plan-cycle-monthly" value="monthly" checked>
                    <label for="plan-cycle-monthly">Mensal</label>
                    <input type="radio" class="lp-cycle-input" name="plan-cycle" id="plan-cycle-annual" value="annual">
                    <label for="plan-cycle-annual">Anual</label>
                </div>
            </fieldset>
        @endif

        <div class="lp-plans">
            <article class="lp-card lp-plan lp-reveal" aria-labelledby="plan-free">
                <h3 class="lp-plan-name" id="plan-free">{{ __('plans.free') }}</h3>
                <p class="lp-plan-tag">Para começar com calma, no ritmo do seu consultório</p>
                <p class="lp-price">R$ 0 <small>para sempre</small></p>
                <p class="lp-price-note">Sem cartão e sem prazo para acabar.</p>
                <p class="lp-plan-key"><i class="bi bi-key" aria-hidden="true"></i>Prontuário cifrado de ponta a ponta desde o primeiro registro</p>
                <ul class="lp-check">
                    <li><strong>Tudo ilimitado:</strong> pacientes, agenda, prontuários e escalas</li>
                    <li>Vídeochamadas com dinâmicas na sessão e relatórios</li>
                    @if($transcriptionEnabled && $freeTranscription > 0)
                        <li>Transcrição da sessão com rascunho do registro: {{ $freeTranscription }} minutos por mês</li>
                    @endif
                    <li>Recibos do Receita Saúde em lote, prontos para o Carnê-Leão</li>
                    <li>Link para o paciente marcar sozinho</li>
                    @foreach(\App\Helpers\MercadoPagoFeeHelper::planFeeLabels(\App\Helpers\PlanHelper::paymentFeePercent('free')) as $feeLabel)
                        <li>{{ $feeLabel }}</li>
                    @endforeach
                </ul>
                <p class="lp-small lp-muted mb-3">Sem cartão, sem limite de uso e sem prazo para acabar.</p>
                <a href="{{ $linkRegister }}" class="lp-btn lp-btn-ghost" data-funnel="landing_cta_register">{{ $ctaRegister }}</a>
            </article>

            <article class="lp-card lp-plan lp-plan-featured lp-reveal" aria-labelledby="plan-professional">
                <h3 class="lp-plan-name" id="plan-professional">{{ $proName }}</h3>
                <p class="lp-plan-tag">Para deixar a parte operacional mais leve</p>
                @if($annual)
                    <div class="lp-plan-cycle" data-cycle="annual">
                        <span class="lp-plan-save">Economize {{ $annualDiscount }}%</span>
                        <p class="lp-price-from">de <s>R$ {{ $annualFull }}</s> por <strong>R$ {{ $annualTotal }}</strong> por 12 meses<br>equivalente a</p>
                        <p class="lp-price">R$ {{ $annualMonthly }} <small>/mês</small></p>
                        <p class="lp-price-note">Fidelidade de 12 meses, sem renovação automática. Em até {{ $annualInstallments }}x sem juros no cartão ou R$ {{ $annualPix }} à vista no Pix.</p>
                    </div>
                @endif
                <div class="lp-plan-cycle" data-cycle="monthly">
                    <p class="lp-price">R$ {{ $proPrice }} <small>/mês</small></p>
                    <p class="lp-price-note">Sem fidelidade: cancele quando quiser.</p>
                </div>
                <p class="lp-plan-key"><i class="bi bi-key" aria-hidden="true"></i>A mesma criptografia de ponta a ponta</p>
                <ul class="lp-check">
                    <li><strong>Tudo do Grátis</strong>, sem a taxa de {{ $freeFee }}% do PSI4 nas cobranças</li>
                    <li>Emissão de documentos</li>
                    <li>Confirmação, lembrete e link da sala automáticos no WhatsApp do paciente (até {{ $proPlan['total_whatsapp_auto'] }} avisos por mês)</li>
                    <li>IA que sugere e você aprova</li>
                    @if($transcriptionEnabled && $proTranscription !== 0)
                        <li>Transcrição da sessão com rascunho do registro: {{ $proTranscription < 0 ? 'ilimitada' : $proTranscription . ' minutos por mês' }}</li>
                    @endif
                    @foreach(\App\Helpers\MercadoPagoFeeHelper::planFeeLabels(\App\Helpers\PlanHelper::paymentFeePercent('professional')) as $feeLabel)
                        <li>
                            {{ $feeLabel }}
                            @if(\App\Helpers\PlanHelper::paymentFeePercent('professional') <= 0)
                                <br><small class="lp-small lp-muted">{{ __('plans.payment_fee_mercado_pago_only') }}</small>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <a href="{{ $linkRegister }}" class="lp-btn lp-btn-primary" data-funnel="landing_cta_register">{{ $ctaRegister }}</a>
                <p class="lp-small lp-muted text-center mt-2 mb-0">Você começa no Grátis e muda para o {{ $proName }} quando quiser.</p>
            </article>
        </div>
    </div>
</section>
