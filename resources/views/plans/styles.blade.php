{{-- Estilos compartilhados das telas de planos (index e opções de pagamento). Usa os tokens de components.css-dash. --}}
<style>
    :root {
        --plan-success: #1c8a56;
        --plan-success-soft: #e4f6ec;
    }
    .plans-wrap { max-width: 960px; margin: 0 auto; }
    .plans-intro { text-align: center; margin: .5rem auto 2rem; max-width: 640px; }
    .plans-intro-title { font-size: 1.9rem; font-weight: 800; color: var(--dash-ink); margin: .35rem 0 .5rem; line-height: 1.25; }
    .plans-intro p { color: var(--dash-muted); font-size: 1.15rem; margin: 0; }

    .plan-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: .6rem; width: 100%;
        border: 0; border-radius: 14px; padding: .9rem 1.5rem; font-weight: 700; font-size: 1.1rem;
        text-decoration: none; transition: background .2s, transform .15s, box-shadow .2s;
    }
    .plan-btn-primary { background: var(--dash-primary); color: #fff; box-shadow: 0 8px 18px rgba(31, 122, 128, .3); }
    .plan-btn-primary:hover, .plan-btn-primary:focus-visible { background: #18656a; color: #fff; transform: translateY(-2px); box-shadow: 0 12px 24px rgba(31, 122, 128, .35); }
    .plan-btn-primary:focus-visible { outline: 3px solid rgba(31, 122, 128, .4); outline-offset: 3px; }
    .plan-btn-primary.disabled { opacity: .75; pointer-events: none; transform: none; }
    .plan-btn-ghost { background: transparent; color: var(--dash-muted); border: 1px solid #dfe5ec; width: auto; }
    .plan-btn-ghost:hover, .plan-btn-ghost:focus-visible { color: var(--dash-ink); border-color: var(--dash-muted); }

    .plan-off { display: inline-flex; align-items: center; padding: .2rem .65rem; border-radius: 20px; font-size: .9rem; font-weight: 700; background: var(--plan-success-soft); color: var(--plan-success); white-space: nowrap; }

    .plans-trust { display: flex; flex-wrap: wrap; justify-content: center; gap: .75rem 2rem; color: var(--dash-muted); font-size: 1.05rem; margin: 2rem 0; }
    .plans-trust span { display: inline-flex; align-items: center; gap: .5rem; }
    .plans-trust i { color: var(--dash-primary); }

    @media (prefers-reduced-motion: reduce) {
        .plan-btn, .plan-btn:hover { transition: none; transform: none; }
    }
</style>
