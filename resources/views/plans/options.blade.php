@extends('layouts.app')
@section('title', 'Escolha como pagar')

@section('header')
    @include('components.css-dash')
    @include('plans.styles')
    <style>
        .options-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
        .option-input { position: absolute; opacity: 0; pointer-events: none; }
        .option-card {
            position: relative; display: flex; flex-direction: column; height: 100%; margin: 0; cursor: pointer;
            background: var(--dash-surface); border: 2px solid var(--dash-line); border-radius: 22px;
            box-shadow: var(--dash-shadow); padding: 1.75rem 1.5rem 1.5rem; transition: border-color .2s, box-shadow .2s, transform .15s;
        }
        .option-card:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(30, 42, 62, 0.1); }
        .option-input:checked + .option-card { border-color: var(--dash-primary); box-shadow: 0 20px 40px -16px rgba(31, 122, 128, .35); }
        .option-input:focus-visible + .option-card { outline: 3px solid rgba(31, 122, 128, .4); outline-offset: 3px; }
        .option-radio {
            position: absolute; top: 1.5rem; right: 1.5rem; width: 24px; height: 24px; border-radius: 50%;
            border: 2px solid #cfd8e3; display: flex; align-items: center; justify-content: center; transition: border-color .2s, background .2s;
        }
        .option-radio i { font-size: .7rem; color: #fff; opacity: 0; }
        .option-input:checked + .option-card .option-radio { border-color: var(--dash-primary); background: var(--dash-primary); }
        .option-input:checked + .option-card .option-radio i { opacity: 1; }
        .option-ribbon {
            position: absolute; top: 0; left: 1.5rem; transform: translateY(-50%); white-space: nowrap;
            background: var(--dash-primary); color: #fff; border-radius: 20px; padding: .25rem .8rem; font-size: .85rem; font-weight: 700;
        }
        .option-title { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; padding-right: 2rem; font-size: 1.3rem; font-weight: 800; color: var(--dash-ink); }
        .option-price { color: var(--dash-ink); font-size: 1.9rem; font-weight: 800; line-height: 1.15; letter-spacing: -.01em; margin: 1rem 0 .35rem; }
        .option-price small { font-size: 1rem; font-weight: 500; color: var(--dash-muted); letter-spacing: 0; }
        .option-note { color: var(--dash-muted); font-size: 1rem; line-height: 1.5; margin: 0; }
        .option-save { align-self: flex-start; margin-top: auto; padding-top: 1rem; }
        .option-save span { display: inline-block; border-radius: 20px; padding: .25rem .8rem; font-size: .95rem; font-weight: 600; background: var(--dash-primary-soft); color: var(--dash-primary); }

        .options-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 2rem; }
        .options-actions .plan-btn-primary { width: auto; padding-inline: 2rem; }
        .options-secure { color: var(--dash-muted); font-size: 1rem; text-align: center; margin-top: 1.25rem; }
        .options-secure i { color: var(--dash-primary); margin-right: .35rem; }

        @media (max-width: 575.98px) {
            .options-actions { flex-direction: column-reverse; align-items: stretch; }
            .options-actions .plan-btn { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            .option-card, .option-card:hover { transition: none; transform: none; }
        }
    </style>
@endsection

@section('content')

    @php($planName = __('plans.'.$planKey))
    @php($price = (float) $plan['price'])
    @php($annual = \App\Helpers\PlanHelper::annualConfig($planKey))
    @php($installments = \App\Helpers\PlanHelper::annualInstallments($planKey))
    @php($annualTotal = \App\Helpers\PlanHelper::annualTotal($planKey))
    @php($annualPix = \App\Helpers\PlanHelper::annualPixTotal($planKey))
    @php($fmt = fn (float $value) => number_format($value, 2, ',', '.'))

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            {{ $options['renewal'] ? 'Renovar' : 'Assinar' }} plano {{ $planName }}
        </h3>
    </div>

    <div class="plans-wrap">
        <section class="plans-intro">
            <div class="dash-eyebrow">Plano {{ $planName }}</div>
            <h4 class="plans-intro-title">Escolha como pagar</h4>
            <p>Quanto mais longo o período, mais você economiza.</p>
        </section>

        <form id="form-checkout-plan" onsubmit="return false;">
            <fieldset>
                <legend class="visually-hidden">Forma de pagamento</legend>
                <div class="options-grid">
                    @if($options['monthly'])
                        <div>
                            <input type="radio" class="option-input" name="option" id="option-monthly" value="monthly" data-cycle="monthly">
                            <label class="option-card" for="option-monthly">
                                <span class="option-radio" aria-hidden="true"><i class="fas fa-check"></i></span>
                                <span class="option-title">Mensal</span>
                                <span class="option-price">R$ {{ $fmt($price) }} <small>/mês</small></span>
                                <span class="option-note">Cobrança recorrente no cartão. Cancele quando quiser.</span>
                            </label>
                        </div>
                    @endif

                    @if($options['annual'])
                        <div>
                            <input type="radio" class="option-input" name="option" id="option-annual-card" value="annual-card" data-cycle="annual" data-payment-method="card" checked>
                            <label class="option-card" for="option-annual-card">
                                <span class="option-ribbon">Mais escolhido</span>
                                <span class="option-radio" aria-hidden="true"><i class="fas fa-check"></i></span>
                                <span class="option-title">1 ano <span class="plan-off">{{ \App\Helpers\PlanHelper::annualDiscount($planKey) }}% OFF</span></span>
                                <span class="option-price">{{ $installments }}x de R$ {{ $fmt(\App\Helpers\PlanHelper::annualInstallmentValue($planKey)) }}</span>
                                <span class="option-note">Total de R$ {{ $fmt($annualTotal) }} no cartão, sem juros. Pagamento único, sem renovação automática.</span>
                                <span class="option-save"><span>Economize R$ {{ $fmt($price * 12 - $annualTotal) }}</span></span>
                            </label>
                        </div>

                        <div>
                            <input type="radio" class="option-input" name="option" id="option-annual-pix" value="annual-pix" data-cycle="annual" data-payment-method="pix">
                            <label class="option-card" for="option-annual-pix">
                                <span class="option-radio" aria-hidden="true"><i class="fas fa-check"></i></span>
                                <span class="option-title">2 anos <span class="plan-off">{{ (int) round((1 - $annualPix / ($price * 12)) * 100) }}% OFF</span></span>
                                <span class="option-price">R$ {{ $fmt($annualPix) }} <small>à vista</small></span>
                                <span class="option-note">Pagamento único no PIX, com o maior desconto.</span>
                                <span class="option-save"><span>Economize R$ {{ $fmt($price * 12 - $annualPix) }}</span></span>
                            </label>
                        </div>
                    @endif
                </div>
            </fieldset>

            <div class="options-actions">
                <a href="{{ route('plans.index') }}" class="plan-btn plan-btn-ghost">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar
                </a>
                <button type="button" class="plan-btn plan-btn-primary" id="btn-continue-checkout" onclick="continueCheckout('{{ $planKey }}')">
                    Continuar para o pagamento <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
            <p class="options-secure">
                <i class="fas fa-lock" aria-hidden="true"></i> Você será direcionado ao Mercado Pago para concluir o pagamento com segurança.
            </p>
        </form>
    </div>

@endsection

@section('footer')
    <x-funnel-tag tag="payment_options" />
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Sem anual disponível, seleciona o mensal por padrão
            if (!document.querySelector('.option-input:checked')) {
                var first = document.querySelector('.option-input');
                if (first) first.checked = true;
            }
        });
    </script>
@endsection
