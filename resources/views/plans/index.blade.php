@extends('layouts.app')
@section('title', 'Planos')

@section('header')
    @include('components.css-dash')
    @include('plans.styles')
    <style>
        /* Subgrid: cada recurso fica na mesma linha em todos os cards, para comparar lado a lado */
        .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); column-gap: 1.5rem; row-gap: 2.5rem; align-items: stretch; }
        .plan-card {
            position: relative; display: grid; grid-template-rows: subgrid; row-gap: 0;
            background: var(--dash-surface); border: 1px solid var(--dash-line); border-radius: 24px;
            box-shadow: var(--dash-shadow); padding: 2rem 1.75rem 1.75rem;
        }
        .plan-card.is-emphasis { border: 2px solid var(--dash-primary); box-shadow: 0 24px 48px -16px rgba(31, 122, 128, .35); }
        .plan-ribbon {
            position: absolute; top: 0; left: 50%; transform: translate(-50%, -50%);
            display: inline-flex; align-items: center; gap: .4rem; white-space: nowrap;
            background: var(--dash-primary); color: #fff; border-radius: 20px; padding: .35rem 1rem;
            font-size: .9rem; font-weight: 700; letter-spacing: .02em; box-shadow: 0 6px 14px rgba(31, 122, 128, .3);
        }
        .plan-name { font-size: 1.5rem; font-weight: 800; color: var(--dash-ink); margin: 0 0 .25rem; }
        .plan-tagline { color: var(--dash-muted); font-size: 1.05rem; margin: 0; }

        .plan-price { padding: 1.25rem 0; }
        .plan-price-old { color: #94a3b8; font-size: 1rem; text-decoration: line-through; min-height: 1.5rem; }
        .plan-price-value { display: flex; align-items: baseline; flex-wrap: wrap; gap: .25rem .5rem; color: var(--dash-ink); }
        .plan-price-amount { font-size: 2.6rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
        .plan-price-amount sup { font-size: 1.1rem; font-weight: 700; top: -1.1em; margin-right: .15rem; }
        .plan-price-period { color: var(--dash-muted); font-size: 1.05rem; }
        .plan-price-note { color: var(--dash-muted); font-size: 1rem; margin-top: .6rem; line-height: 1.5; }
        .plan-price-note strong { color: var(--dash-ink); font-weight: 600; }

        .plan-cta { padding-bottom: 1.25rem; border-bottom: 1px solid var(--dash-line); margin-bottom: .5rem; }
        .plan-current {
            display: flex; align-items: center; justify-content: center; gap: .5rem; min-height: 52px;
            border-radius: 14px; background: var(--dash-primary-soft); color: var(--dash-primary); font-weight: 700; font-size: 1.05rem;
        }
        .plan-cta-hint { display: flex; align-items: center; justify-content: center; min-height: 52px; color: var(--dash-muted); font-size: 1rem; text-align: center; }

        .plan-features { display: contents; }
        .plan-feature { display: flex; align-items: flex-start; gap: .75rem; padding: .6rem 0; font-size: 1.1rem; line-height: 1.45; color: var(--dash-ink); }
        .plan-feature-icon {
            flex: 0 0 22px; height: 22px; margin-top: .1rem; border-radius: 50%; font-size: .7rem;
            display: flex; align-items: center; justify-content: center; background: var(--plan-success-soft); color: var(--plan-success);
        }
        .plan-feature-note { display: block; font-size: .95rem; color: var(--dash-muted); }
        .plan-feature.is-missing { color: #9aa7b8; }
        .plan-feature.is-missing .plan-feature-icon { background: #f2f4f7; color: #b3bdca; }

        .plan-footer { padding-top: 1rem; }
        .plan-link-cancel { background: none; border: 0; padding: 0; color: var(--dash-muted); font-size: .95rem; text-decoration: underline; }
        .plan-link-cancel:hover, .plan-link-cancel:focus-visible { color: #d64545; }
        .plan-notice { border-radius: 14px; background: #fff4d9; color: #7a5300; padding: .85rem 1rem; font-size: 1rem; line-height: 1.5; }

        .plans-pending { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; border-left: 4px solid #b27a00; margin-bottom: 2.5rem; }
        .plans-pending-icon { width: 44px; height: 44px; border-radius: 14px; background: #fff4d9; color: #b27a00; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .plans-pending .plan-btn { width: auto; padding: .7rem 1.25rem; font-size: 1rem; }

        .plans-faq .accordion-item { border: 0; border-bottom: 1px solid var(--dash-line); background: transparent; }
        .plans-faq .accordion-item:last-child { border-bottom: 0; }
        .plans-faq .accordion-button { background: transparent; box-shadow: none; padding: 1rem .25rem; font-weight: 600; font-size: 1.2rem; color: var(--dash-ink); }
        .plans-faq .accordion-button:not(.collapsed) { color: var(--dash-primary); }
        .plans-faq .accordion-button:focus-visible { outline: 2px solid var(--dash-primary); outline-offset: 2px; border-radius: 8px; }
        .plans-faq .accordion-body { padding: 0 .25rem 1.1rem; color: var(--dash-muted); font-size: 1.1rem; line-height: 1.6; }
        .plans-faq .accordion-body strong { color: var(--dash-ink); }

        @media (max-width: 767.98px) {
            .plan-card.is-emphasis { order: -1; }
            .plans-intro-title { font-size: 1.5rem; }
        }
        @media (max-width: 575.98px) {
            .plan-card { padding: 2rem 1.25rem 1.25rem; border-radius: 20px; }
            .plan-price-amount { font-size: 2.2rem; }
            .plans-pending .plan-btn { width: 100%; }
        }
    </style>
@endsection

@section('content')
    @if(!empty($transcriptionUsage) && $transcriptionUsage['limit'] !== 0)
        <p class="small text-muted" id="transcription-usage">
            @if($transcriptionUsage['limit'] < 0)
                {{ __('session-transcriptions.usage_unlimited', ['used' => $transcriptionUsage['used']]) }}
            @else
                {{ __('session-transcriptions.usage', ['used' => $transcriptionUsage['used'], 'limit' => $transcriptionUsage['limit']]) }}
            @endif
        </p>
    @endif


    @php($money = fn (float $value) => number_format($value, 2, ',', '.'))

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">Planos</h3>
    </div>

    <div class="plans-wrap">
        <section class="plans-intro">
            <div class="dash-eyebrow">Planos PSI4</div>
            <h4 class="plans-intro-title">Escolha o plano ideal para o seu consultório</h4>
            <p>Comece no plano Grátis, para sempre e sem cartão. Sem fidelidade no plano mensal.</p>
        </section>

        @if(!empty($pendingSubscription))
            @php($pendingOption = $pendingSubscription->cycle === \App\Enums\PlanCycleEnum::ANNUAL
                ? 'anual '.($pendingSubscription->payment_method === \App\Enums\PaymentMethodEnum::PIX ? 'no PIX' : 'no cartão')
                : 'mensal')
            <div class="dash-card plans-pending" role="status">
                <div class="d-flex align-items-center gap-3">
                    <div class="plans-pending-icon" aria-hidden="true"><i class="fas fa-hourglass-half"></i></div>
                    <span>
                        Você tem uma assinatura do plano <strong>{{ __('plans.'.$pendingSubscription->plan->value) }}</strong> ({{ $pendingOption }}) aguardando confirmação do pagamento.
                    </span>
                </div>
                <a href="{{ $pendingSubscription->init_point }}" id="btn-continue-payment" class="plan-btn plan-btn-primary" data-plan-loading>Continuar pagamento</a>
            </div>
        @endif

        <div class="plans-grid">
            @foreach($plans as $plan)
                <article class="plan-card {{ $plan['emphasis'] ? 'is-emphasis' : '' }} {{ $plan['current'] ? 'is-current' : '' }}"
                         style="grid-row: span {{ count($plan['features']) + 4 }}"
                         data-plan="{{ $plan['key'] }}" aria-labelledby="plan-name-{{ $plan['key'] }}">
                    @if($plan['emphasis'])
                        <span class="plan-ribbon"><i class="fas fa-star" aria-hidden="true"></i> Mais escolhido</span>
                    @endif

                    <header>
                        <h4 class="plan-name" id="plan-name-{{ $plan['key'] }}">{{ $plan['name'] }}</h4>
                        <p class="plan-tagline">{{ $plan['tagline'] }}</p>
                    </header>

                    <div class="plan-price">
                        @if($plan['annual'])
                            <div class="plan-price-old">de R$ {{ $money($plan['price']) }}/mês</div>
                            <div class="plan-price-value">
                                <span class="plan-price-amount"><sup>R$</sup>{{ $money($plan['annual']['monthly_equivalent']) }}</span>
                                <span class="plan-price-period">/mês</span>
                                <span class="plan-off">{{ $plan['annual']['discount'] }}% OFF</span>
                            </div>
                            <div class="plan-price-note">
                                no plano anual (<strong>R$ {{ $money($plan['annual']['total']) }}</strong> por ano)<br>
                                ou R$ {{ $money($plan['price']) }} no plano mensal
                            </div>
                        @else
                            <div class="plan-price-old" aria-hidden="true"></div>
                            <div class="plan-price-value">
                                <span class="plan-price-amount"><sup>R$</sup>{{ $plan['price'] > 0 ? $money($plan['price']) : '0' }}</span>
                                <span class="plan-price-period">/mês</span>
                            </div>
                            <div class="plan-price-note">
                                {{ $plan['price'] > 0 ? 'Cobrança mensal no cartão' : 'Para sempre, sem cartão' }}
                            </div>
                        @endif
                    </div>

                    <div class="plan-cta">
                        @if($plan['action'] === 'subscribe' || $plan['action'] === 'renew')
                            <a href="{{ route('plans.options', $plan['key']) }}" class="plan-btn plan-btn-primary" data-plan-loading>
                                {{ $plan['action'] === 'renew' ? 'Renovar plano anual' : 'Assinar plano '.$plan['name'] }}
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        @elseif($plan['action'] === 'current')
                            <div class="plan-current"><i class="fas fa-check-circle" aria-hidden="true"></i> Seu plano atual</div>
                        @else
                            <div class="plan-cta-hint">Incluso para todos, sem custo</div>
                        @endif
                    </div>

                    <ul class="plan-features" role="list">
                        @foreach($plan['features'] as $feature)
                            <li class="plan-feature {{ $feature['included'] ? '' : 'is-missing' }}">
                                <span class="plan-feature-icon" aria-hidden="true">
                                    <i class="fas {{ $feature['included'] ? 'fa-check' : 'fa-times' }}"></i>
                                </span>
                                <span>
                                    {{ $feature['label'] }}
                                    @unless($feature['included'])<span class="visually-hidden">(não incluso)</span>@endunless
                                    @isset($feature['note'])<small class="plan-feature-note">{{ $feature['note'] }}</small>@endisset
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Última linha do subgrid: sempre renderizada para os cards ocuparem as mesmas linhas --}}
                    @if($plan['renewal_cancelled'])
                        <div class="plan-footer">
                            <div class="plan-notice" id="alert-renewal-cancelled">
                                Renovação cancelada. Seu plano termina em <strong>{{ $plan['expiration_date'] }}</strong> e sua conta volta para o plano Grátis, sem perder nenhum registro.
                            </div>
                        </div>
                    @elseif($plan['can_cancel_renewal'])
                        <div class="plan-footer">
                            <button type="button" id="btn-cancel-renewal" class="plan-link-cancel"
                                    onclick="cancelPlanRenewal('{{ $plan['expiration_date'] }}')">
                                Cancelar renovação
                            </button>
                        </div>
                    @else
                        <div aria-hidden="true"></div>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="plans-trust">
            <span><i class="fas fa-lock" aria-hidden="true"></i> Pagamento seguro via Mercado Pago</span>
            <span><i class="far fa-credit-card" aria-hidden="true"></i> Cartão de crédito ou PIX</span>
            <span><i class="fas fa-shield-alt" aria-hidden="true"></i> Prontuários criptografados</span>
        </div>

        @include('plans.faq')
    </div>

@endsection

@section('footer')
    <x-funnel-tag tag="plans" />
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            bindPlanLinksLoading();
        });
    </script>
@endsection
