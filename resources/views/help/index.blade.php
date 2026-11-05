@extends('layouts.app')
@section('title', 'Ajuda')

@section('header')
    @include('components.css-dash')
    <style>
        .help-hero { position: relative; overflow: hidden; padding: 2rem; }
        .help-hero::after {
            content: ''; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; border-radius: 50%;
            background: radial-gradient(circle, rgba(37, 211, 102, .14), rgba(37, 211, 102, 0) 70%); pointer-events: none;
        }
        .help-hero-icon {
            width: 64px; height: 64px; border-radius: 20px; background: #e4f6ec; color: #1c8a56;
            display: flex; align-items: center; justify-content: center; font-size: 2rem; flex-shrink: 0;
        }
        .help-hero h4 { font-weight: 700; color: var(--dash-ink); margin: 0; font-size: 1.6rem; }
        .help-hero p { color: var(--dash-muted); font-size: 1.2rem; line-height: 1.6; }
        .help-status { font-size: 1rem; padding: .35rem .9rem; }
        .help-status-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
        .help-status.is-online .help-status-dot { animation: help-pulse 1.8s ease-out infinite; }
        @keyframes help-pulse {
            0% { box-shadow: 0 0 0 0 rgba(28, 138, 86, .45); }
            100% { box-shadow: 0 0 0 8px rgba(28, 138, 86, 0); }
        }
        .help-btn-whatsapp {
            display: inline-flex; align-items: center; justify-content: center; gap: .6rem;
            background: #25d366; border: 0; color: #fff; border-radius: 14px; padding: .9rem 1.75rem;
            font-weight: 700; font-size: 1.25rem; white-space: nowrap; box-shadow: 0 6px 16px rgba(37, 211, 102, .35);
            transition: background .2s, transform .15s, box-shadow .2s;
        }
        .help-btn-whatsapp:hover, .help-btn-whatsapp:focus-visible {
            background: #1ebe5a; color: #fff; transform: translateY(-2px); box-shadow: 0 10px 22px rgba(37, 211, 102, .4);
        }
        .help-btn-whatsapp:focus-visible { outline: 3px solid rgba(37, 211, 102, .45); outline-offset: 3px; }
        .help-btn-whatsapp i { font-size: 1.4rem; }
        .help-hint { color: var(--dash-muted); font-size: 1.10rem; }

        .help-code {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem;
            background: var(--dash-primary-soft); border-radius: 16px; padding: .75rem .75rem .75rem 1.25rem;
        }
        .help-code-value {
            font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: 1.4rem; font-weight: 700;
            letter-spacing: .08em; color: var(--dash-primary); white-space: nowrap;
        }
        .help-code .btn { border-radius: 12px; font-weight: 600; white-space: nowrap; }

        .help-schedule .dash-row { justify-content: space-between; }
        .help-schedule .dash-row-detail { color: var(--dash-ink); font-weight: 600; }
        .help-schedule .is-closed { color: var(--dash-muted); font-weight: 500; }

        .help-links .dash-row { text-decoration: none; }
        .help-links .dash-row:hover .dash-row-name { color: var(--dash-primary); }
        .help-links .dash-row > .fa-chevron-right { color: #b8c9cc; font-size: .85rem; }

        .help-search { position: relative; }
        .help-search > i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--dash-muted); pointer-events: none; }
        .help-search .form-control { padding: 0 1rem 0 2.6rem; border-radius: 12px; height: 46px; }

        .help-accordion .accordion-item { border: 0; border-bottom: 1px solid var(--dash-line); background: transparent; }
        .help-accordion .accordion-item:last-child { border-bottom: 0; }
        .help-accordion .accordion-button {
            background: transparent; box-shadow: none; padding: 1rem .25rem; font-weight: 600; font-size: 1.2rem; color: var(--dash-ink);
        }
        .help-accordion .accordion-button:not(.collapsed) { color: var(--dash-primary); }
        .help-accordion .accordion-button:focus-visible { outline: 2px solid var(--dash-primary); outline-offset: 2px; border-radius: 8px; }
        .help-accordion .accordion-body { padding: 0 .25rem 1.1rem; color: var(--dash-muted); font-size: 1.15rem; line-height: 1.6; }
        .help-accordion .accordion-body strong { color: var(--dash-ink); }
        .help-link { color: var(--dash-primary); font-weight: 600; }

        @media (max-width: 575.98px) {
            .help-hero { padding: 1.5rem 1.25rem; }
            .help-btn-whatsapp { width: 100%; white-space: normal; }
        }
        @media (prefers-reduced-motion: reduce) {
            .help-status.is-online .help-status-dot { animation: none; }
            .help-btn-whatsapp, .help-btn-whatsapp:hover { transition: none; transform: none; }
        }
    </style>
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-help', ['css' => 'me-2']) Ajuda
        </h3>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8 d-flex flex-column gap-4">
            <section class="dash-card help-hero" aria-labelledby="help-hero-title">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="help-hero-icon" aria-hidden="true">@include('components.iacon-whatsapp')</div>
                    <div>
                        <div class="dash-eyebrow">Atendimento humano</div>
                        <h4 id="help-hero-title">Como podemos ajudar?</h4>
                    </div>
                </div>

                <p class="mb-3">
                    Aqui você fala com pessoas de verdade, não com robôs. Chame nossa equipe no WhatsApp e resolva sua dúvida rapidinho.
                </p>

                <span id="help-status" role="status"
                      class="dash-chip help-status mb-4 {{ $supportStatus['online'] ? 'dash-chip-success is-online' : 'dash-chip-warning' }}">
                    <span class="help-status-dot" aria-hidden="true"></span> {{ $supportStatus['label'] }}
                </span>

                <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                    <a href="{{ route('whatsapp.help') }}" target="_blank" rel="noopener"
                       class="help-btn-whatsapp" id="help-whatsapp-btn" aria-describedby="help-whatsapp-hint">
                        @include('components.iacon-whatsapp') Iniciar conversa no WhatsApp
                    </a>
                    <span class="help-hint" id="help-whatsapp-hint">
                        <i class="fas fa-external-link-alt me-1" aria-hidden="true"></i> Abre em uma nova aba, já com seu código de suporte.
                    </span>
                </div>
            </section>

            @include('help.faq')
        </div>

        <div class="col-12 col-xl-4 d-flex flex-column gap-4">
            @if($supportCode)
                <section class="dash-card" aria-labelledby="help-code-title">
                    <div class="dash-title" id="help-code-title"><i class="fas fa-id-badge" aria-hidden="true"></i> Seu código de suporte</div>
                    <div class="help-code">
                        <span class="help-code-value" id="help-support-code">{{ $supportCode }}</span>
                        <button type="button" class="btn btn-primary" id="help-copy-code"
                                onclick="copyToClipboard(@js($supportCode), 'Código copiado')">
                            <i class="far fa-copy me-1" aria-hidden="true"></i> Copiar
                        </button>
                    </div>
                    <p class="help-hint mt-3 mb-0">Informe este código ao atendente para localizarmos sua conta mais rápido.</p>
                </section>
            @endif

            <section class="dash-card help-schedule" aria-labelledby="help-schedule-title">
                <div class="dash-title" id="help-schedule-title"><i class="far fa-clock" aria-hidden="true"></i> Horário de atendimento</div>
                @foreach($supportSchedule as $row)
                    <div class="dash-row">
                        <span class="dash-row-name">{{ $row['days'] }}</span>
                        <span class="dash-row-detail {{ $row['hours'] === __('help.closed') ? 'is-closed' : '' }}">{{ $row['hours'] }}</span>
                    </div>
                @endforeach
                <p class="help-hint mt-2 mb-0">Fora do horário, pode mandar mensagem: respondemos assim que voltarmos.</p>
            </section>

            <section class="dash-card help-links" aria-labelledby="help-links-title">
                <div class="dash-title" id="help-links-title"><i class="far fa-file-alt" aria-hidden="true"></i> Links úteis</div>
                <a href="{{ route('terms-service') }}" target="_blank" rel="noopener" class="dash-row">
                    <span class="dash-round-btn dash-round-btn-primary" aria-hidden="true"><i class="fas fa-file-contract"></i></span>
                    <span class="dash-row-body"><span class="dash-row-name">Termos de uso</span></span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" class="dash-row">
                    <span class="dash-round-btn dash-round-btn-primary" aria-hidden="true"><i class="fas fa-user-shield"></i></span>
                    <span class="dash-row-body"><span class="dash-row-name">Política de privacidade</span></span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>
            </section>
        </div>
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const search = document.getElementById('help-faq-search');
            const empty = document.getElementById('help-faq-empty');
            const items = document.querySelectorAll('[data-help-faq]');
            const normalize = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

            if (!search) return;

            search.addEventListener('input', function () {
                const term = normalize(search.value);
                let visible = 0;

                items.forEach(function (item) {
                    const match = !term || normalize(item.textContent).includes(term);
                    item.classList.toggle('d-none', !match);
                    if (match) visible++;
                });

                empty.classList.toggle('d-none', visible > 0);
            });
        });
    </script>
@endsection
