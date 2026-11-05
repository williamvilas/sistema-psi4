<style>
    .pat-breadcrumb { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; color: var(--dash-muted); margin-bottom: 1rem; }
    .pat-breadcrumb a { color: var(--dash-primary); font-weight: 600; text-decoration: none; }
    .pat-breadcrumb a:hover { text-decoration: underline; }
    .pat-breadcrumb .fa-chevron-right { font-size: .7rem; }
    .pat-form-card { padding: 1.75rem; }
    .pat-form-head { display: flex; align-items: center; gap: .9rem; padding-bottom: 1.25rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--dash-line); }
    .pat-form-head .dash-avatar { width: 56px; height: 56px; font-size: 1.2rem; }
    .pat-form-head-body { min-width: 0; }
    .pat-form-head-name { font-size: 1.3rem; font-weight: 700; color: var(--dash-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pat-form-head-sub { color: var(--dash-muted); }
    .pat-form-legend { color: var(--dash-muted); font-size: .95rem; margin-bottom: 1rem; }
    .pat-form-section + .pat-form-section { border-top: 1px solid var(--dash-line); padding-top: 1.25rem; margin-top: .25rem; }
    .pat-form-section .dash-title { font-size: 1.15rem; margin-bottom: .75rem; }
    .pat-form label { font-weight: 600; color: var(--dash-ink); }
    .pat-input { position: relative; }
    .pat-input > i { position: absolute; left: 1rem; top: 27.5px; transform: translateY(-50%); color: var(--dash-muted); pointer-events: none; font-size: 1.6rem;}
    .pat-input .form-control { padding: 0 1rem 0 3.0rem; border-radius: 12px; } /* altura global de 55px (app.css) */
    .pat-input .form-control.is-invalid { padding-right: 2.6rem; background-position: right 1rem center; }
    .pat-form .form-textarea { border-radius: 12px; }
    .pat-form .form-control:focus, .pat-form .form-textarea:focus { border-color: var(--dash-primary); box-shadow: 0 0 0 .2rem var(--dash-primary-soft); }
    .pat-hint { display: block; color: var(--dash-muted); margin-top: .35rem; }
    .pat-hint-link { color: var(--dash-primary); font-weight: 600; text-decoration: none; margin-left: .25rem; white-space: nowrap; }
    .pat-form-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .5rem; padding-top: 1.25rem; margin-top: .5rem; border-top: 1px solid var(--dash-line); }
    .pat-form-actions .btn { border-radius: 12px; padding: .6rem 1.25rem; font-weight: 600; }
    .pat-form-actions .btn-primary { min-width: 140px; }
    /* Tela de detalhes (show) */
    .pat-show-card { padding: 1.75rem; }
    .pat-show-head { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--dash-line); }
    .pat-show-head .dash-avatar { width: 64px; height: 64px; font-size: 1.4rem; }
    .pat-show-head-body { flex: 1 1 220px; min-width: 0; }
    .pat-show-name { font-size: 1.5rem; font-weight: 700; color: var(--dash-ink); margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pat-show-sub { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; color: var(--dash-muted); margin-top: .2rem; }
    .pat-show-actions { display: flex; align-items: center; gap: .5rem; margin-left: auto; }
    .pat-show-actions .btn { border-radius: 12px; padding: .55rem 1.1rem; font-weight: 600; }
    .pat-show-actions .btn-whatsapp { background: #e4f6ec; color: #1c8a56; border: 0; }
    .pat-show-actions .btn-whatsapp:hover, .pat-show-actions .btn-whatsapp:focus { background: #1c8a56; color: #fff; }
    .pat-show-alert { margin: 1.25rem 0 0; border-radius: 12px; }
    .pat-show-section { padding-top: 1.25rem; margin-top: 1.25rem; border-top: 1px solid var(--dash-line); }
    .pat-show-head + .pat-show-section, .pat-show-alert + .pat-show-section { border-top: 0; margin-top: 0; }
    .pat-show-section-title { font-size: 1.15rem; margin-bottom: .5rem; }
    .pat-show-field { display: flex; align-items: center; gap: .75rem; padding: .55rem 0; }
    .pat-show-field-icon { width: 40px; height: 40px; border-radius: 12px; background: var(--dash-primary-soft); color: var(--dash-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .pat-show-field-body { flex: 1; min-width: 0; }
    .pat-show-label { display: block; font-size: .85rem; color: var(--dash-muted); text-transform: uppercase; letter-spacing: .03em; font-weight: 600; }
    .pat-show-value { display: block; font-size: 1.05rem; font-weight: 600; color: var(--dash-ink); overflow-wrap: anywhere; }
    .pat-show-value a { color: var(--dash-ink); text-decoration: none; }
    .pat-show-value a:hover { color: var(--dash-primary); text-decoration: underline; }
    .pat-show-empty { color: #a3afbf; font-weight: 400; font-style: italic; }
    .pat-show-copy { width: 34px; height: 34px; font-size: .95rem; }
    .pat-show-notes { white-space: normal; color: var(--dash-ink); background: #f7f9fb; border-radius: 12px; padding: 1rem; margin: 0; overflow-wrap: anywhere; }
    @media (max-width: 767.98px) {
        .pat-show-card { padding: 1.25rem; }
        .pat-show-name { white-space: normal; }
        /* no celular as ações ficam fixas na base da tela, sempre ao alcance do polegar */
        .pat-show-actions {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030; background: var(--dash-surface);
            padding: .75rem 1rem; box-shadow: 0 -6px 16px rgba(30, 42, 62, 0.08);
        }
        .pat-show-actions .btn { flex: 1 1 auto; }
        .pat-show-page { padding-bottom: 5rem; } /* espaço para a barra fixa não cobrir o conteúdo */
    }
    @media (max-width: 767.98px) {
        .pat-form-card { padding: 1.25rem; }
        /* no celular as ações ficam fixas na base da tela, sempre ao alcance do polegar */
        .pat-form-actions {
            position: sticky; bottom: 0; z-index: 5; background: var(--dash-surface);
            margin: .5rem -1.25rem -1.25rem; padding: .75rem 1.25rem; border-radius: 0 0 var(--dash-radius) var(--dash-radius);
            box-shadow: 0 -6px 16px rgba(30, 42, 62, 0.06);
        }
        .pat-form-actions .btn { flex: 1 1 auto; }
        .pat-form-actions .btn-primary { order: -1; flex-basis: 100%; }
        .pat-form-actions #btn-store-patients-and-new { order: 0; flex-basis: 100%; }
        .pat-form-actions .btn-outline-secondary { order: 1; flex-basis: 100%; }
    }
    /* Painel de evolução */
    .pat-evo { margin-top: 1.5rem; }
    .pat-evo-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
    .pat-evo-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .pat-evo-actions .form-select, .pat-evo-actions .form-select-46px { width: auto; min-width: 200px; border-radius: 12px; }
    .pat-evo-actions .btn { border-radius: 12px; font-weight: 600; }
    .pat-evo-hint, .pat-evo-since { color: var(--dash-muted); margin: .5rem 0 1rem; }
    .pat-evo-indicators.is-loading { opacity: .5; }
    .pat-evo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; }
    @media (min-width: 768px) { .pat-evo-grid { grid-template-columns: repeat(3, 1fr); } }
    .pat-evo-tile { background: var(--dash-primary-soft); border-radius: 14px; padding: .75rem 1rem; }
    .pat-evo-tile strong { display: block; font-size: 1.35rem; color: var(--dash-ink); }
    .pat-evo-label { color: var(--dash-muted); font-size: 1.1rem; }
    .pat-evo-scales { border-top: 1px solid var(--dash-line); margin-top: 1.25rem; padding-top: 1.25rem; }
    .pat-evo-subtitle { font-weight: 700; color: var(--dash-ink); margin-bottom: .75rem; }
    .pat-evo-subtitle .fa-lock { color: var(--dash-primary); font-size: 1.0rem; }
    .pat-evo-locked { background: #f7f9fb; border-radius: 14px; padding: 1rem; color: var(--dash-muted); }
    .pat-evo-chart { min-height: 280px; margin-bottom: 1rem; }
    .pat-scale-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; padding: .75rem 0; border-bottom: 1px solid var(--dash-line); }
    .pat-scale-row:last-child { border-bottom: 0; }
    .pat-scale-row-main { display: flex; flex-direction: column; min-width: 120px; }
    .pat-scale-date { color: var(--dash-muted); font-size: 1.0rem; }
    .pat-scale-row-main strong {font-size: 1.0rem;}
    .pat-scale-date i {font-size: 1.0rem;}
    .pat-scale-row-result { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; flex: 1 1 auto; }
    .pat-scale-score { font-size: 1.2rem; font-weight: 700; color: var(--dash-ink); }
    .pat-scale-score small { font-size: .8rem; color: var(--dash-muted); font-weight: 400; }
    .pat-scale-diff { font-size: .85rem; color: var(--dash-muted); }
    .pat-scale-diff.is-better { color: #1c8a56; }
    .pat-scale-diff.is-worse { color: #b27a00; }
    /* Modal de aplicação de escala */
    .pat-scale-modal .modal-content { border: 0; border-radius: var(--dash-radius); }
    .pat-scale-modal .modal-header { border-bottom-color: var(--dash-line); }
    .pat-scale-modal .modal-title { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-weight: 700; color: var(--dash-ink); }
    .pat-scale-modal .modal-title > .fa-clipboard-list { color: var(--dash-primary); }
    .pat-scale-lock { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .6rem; border-radius: 20px; background: var(--dash-primary-soft); color: var(--dash-primary); font-size: .9rem; font-weight: 600; }
    .pat-scale-modal .form-control { border-radius: 12px; }
    .pat-scale-legend { float: none; font-size: 1.1rem; color: var(--dash-ink); }
    .pat-scale-picker { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; }
    @media (min-width: 992px) { .pat-scale-picker { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .pat-scale-choice { display: flex; flex-direction: column; gap: .2rem; height: 100%; margin: 0; padding: .85rem 1rem; border: 1px solid #dfe5ec; border-radius: 14px; background: #fff; cursor: pointer; user-select: none; transition: background .15s, border-color .15s, box-shadow .15s; }
    .pat-scale-choice strong { color: var(--dash-ink); font-size: 1.15rem; }
    .pat-scale-choice span { color: var(--dash-muted); font-size: 1.05rem; line-height: 1.3; }
    .pat-scale-choice small { color: var(--dash-muted); font-size: .95rem; margin-top: auto; padding-top: .35rem; }
    .pat-scale-choice:hover { border-color: var(--dash-primary); }
    .btn-check:checked + .pat-scale-choice { border-color: var(--dash-primary); background: var(--dash-primary-soft); box-shadow: inset 0 0 0 1px var(--dash-primary); }
    .btn-check:checked + .pat-scale-choice strong { color: var(--dash-primary); }
    .btn-check:focus-visible + .pat-scale-choice { box-shadow: 0 0 0 .2rem var(--dash-primary-soft); }
    /* Instrução e progresso ficam visíveis enquanto a lista de itens rola */
    .pat-scale-sticky { position: sticky; top: -1rem; z-index: 2; margin: 0 -1rem; padding: 1rem; background: var(--dash-surface); border-bottom: 1px solid var(--dash-line); }
    .pat-scale-instruction { font-size: 1.15rem; line-height: 1.45; font-weight: 600; color: var(--dash-ink); }
    .pat-scale-progress { height: 6px; border-radius: 6px; background: var(--dash-line); overflow: hidden; }
    .pat-scale-progress-bar { width: 0; height: 100%; border-radius: 6px; background: var(--dash-primary); transition: width .25s ease; }
    .pat-scale-item { border: 1px solid var(--dash-line); border-radius: 14px; padding: 1.25rem; margin: 1.1rem 0 0; transition: border-color .15s, background .15s; }
    .pat-scale-item legend { display: flex; align-items: flex-start; gap: .75rem; float: none; font-size: 1.2rem; line-height: 1.45; font-weight: 500; margin-bottom: 1rem; color: var(--dash-ink); }
    .pat-scale-item-number { flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #eef1f4; color: var(--dash-muted); font-size: 1rem; font-weight: 700; transition: background .15s, color .15s; }
    .pat-scale-item.is-answered .pat-scale-item-number { background: var(--dash-primary); color: #fff; }
    .pat-scale-item.is-missing { border-color: #f0c36d; background: #fffbf0; }
    .pat-scale-item.is-missing .pat-scale-item-number { background: #fff4d9; color: #b27a00; }
    /* Rótulos curtos (PHQ-9, GAD-7, PCL-5): uma coluna por opção; rótulos longos (DASS-21): 2 colunas, 1 no celular */
    .pat-scale-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; }
    @media (min-width: 992px) { .pat-scale-options { grid-template-columns: repeat(var(--scale-options, 4), minmax(0, 1fr)); } }
    .pat-scale-options-long .pat-scale-options { grid-template-columns: 1fr; }
    @media (min-width: 576px) { .pat-scale-options-long .pat-scale-options { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .pat-scale-option { display: flex; align-items: center; gap: .65rem; min-height: 52px; height: 100%; margin: 0; padding: .7rem .9rem; border: 1px solid #dfe5ec; border-radius: 12px; background: #fff; color: var(--dash-ink); font-size: 1.1rem; line-height: 1.35; cursor: pointer; user-select: none; transition: background .15s, border-color .15s, color .15s; }
    .pat-scale-option-value { flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 8px; background: #eef1f4; color: var(--dash-muted); font-size: 1rem; font-weight: 700; }
    .pat-scale-option:hover { border-color: var(--dash-primary); color: var(--dash-primary); }
    .btn-check:checked + .pat-scale-option { background: var(--dash-primary); border-color: var(--dash-primary); color: #fff; font-weight: 600; }
    .btn-check:checked + .pat-scale-option .pat-scale-option-value { background: rgba(255, 255, 255, .22); color: #fff; }
    .btn-check:focus-visible + .pat-scale-option { box-shadow: 0 0 0 .2rem var(--dash-primary-soft); }
    .pat-scale-critical { display: flex; gap: .75rem; align-items: flex-start; margin: 1rem 0 0; border-radius: 12px; font-size: 1.1rem; line-height: 1.45; }
    .pat-scale-critical i { margin-top: .2rem; }
    .pat-scale-footer { justify-content: flex-end; flex-wrap: wrap; gap: .5rem; border-top-color: var(--dash-line); }
    .pat-scale-footer-actions { display: flex; gap: .5rem; margin-left: auto; }
    .pat-scale-footer-actions .btn { border-radius: 12px; font-weight: 600; }
    .pat-scale-summary { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem .6rem; margin-right: auto; color: var(--dash-muted); font-size: 1.05rem; }
    .pat-scale-summary strong { font-size: 1.4rem; color: var(--dash-ink); }
    .pat-scale-footer-actions .btn { font-size: 1.05rem; padding: .55rem 1.25rem; }
    .pat-scale-note { color: var(--dash-muted); font-size: 1rem; line-height: 1.45; }

    /* Escala enviada por link */
    .pat-scale-link-intro { color: var(--dash-muted); font-size: 1.15rem; line-height: 1.5; margin-bottom: 1rem; }
    .pat-scale-link-result { background: var(--dash-primary-soft); border-radius: 14px; padding: 1rem; margin-bottom: 1.25rem; }
    .pat-scale-link-result .form-label { color: var(--dash-primary); }
    .pat-scale-link-copy { display: flex; flex-wrap: wrap; gap: .5rem; }
    .pat-scale-link-copy .form-control { flex: 1 1 260px; min-width: 0; border-radius: 12px; background: #fff; font-size: .95rem; }
    .pat-scale-link-copy .btn { border-radius: 12px; font-weight: 600; }
    .pat-scale-link-copy .btn.is-copied { background: #146c43; border-color: #146c43; }
    .pat-scale-link-history { border-top: 1px solid var(--dash-line); padding-top: 1rem; }
    .pat-scale-link-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; padding: .65rem 0; border-bottom: 1px solid var(--dash-line); }
    .pat-scale-link-row:last-child { border-bottom: 0; }
    .pat-scale-link-actions { display: flex; gap: .35rem; margin-left: auto; }
    .pat-scale-link-pagination { display: flex; align-items: center; justify-content: center; gap: .75rem; margin-top: .75rem; }
    .pat-scale-link-page { color: var(--dash-muted); font-size: .9rem; }
    .pat-evo-actions .btn-outline-primary { border-radius: 12px; font-weight: 600; }
</style>
