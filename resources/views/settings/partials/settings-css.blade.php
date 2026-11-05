<style>
    /* Menu lateral de seções */
    .cfg-nav { padding: .75rem; position: sticky; top: 1rem; flex-direction: column; flex-wrap: nowrap; }
    .cfg-nav-group { color: var(--dash-muted); text-transform: uppercase; font-size: .8rem; font-weight: 700; letter-spacing: .06em; padding: .75rem .75rem .35rem; }
    .cfg-nav-group:first-child { padding-top: .25rem; }
    .cfg-nav-items { display: flex; flex-direction: column; gap: .15rem; }
    .cfg-nav .cfg-nav-link { display: flex; align-items: center; gap: .65rem; width: 100%; text-align: left; border: 0; border-radius: 12px; padding: .65rem .75rem; color: var(--dash-ink); font-weight: 600; background: transparent; transition: background .15s, color .15s; }
    .cfg-nav .cfg-nav-link i { width: 1.25rem; text-align: center; color: var(--dash-muted); transition: color .15s; }
    .cfg-nav .cfg-nav-link:hover { background: #f2f5f8; color: var(--dash-ink); }
    .cfg-nav .cfg-nav-link.active { background: var(--dash-primary-soft); color: var(--dash-primary); }
    .cfg-nav .cfg-nav-link.active i { color: var(--dash-primary); }
    .cfg-nav-label { flex: 1; min-width: 0; }
    .cfg-nav-status { width: 8px; height: 8px; border-radius: 50%; background: #c9d2dc; flex-shrink: 0; }
    .cfg-nav-status.is-on { background: #1c8a56; }
    .cfg-nav-status.is-warning { background: #e0a100; }
    .cfg-nav-dirty { display: none; font-size: .75rem; font-weight: 700; color: #b27a00; }
    .cfg-nav-link.is-dirty .cfg-nav-dirty { display: inline; }

    /* Painéis: os painéis de Agenda/Feriados ficam dentro do mesmo <form> */
    .cfg-content.tab-content { padding: 0; } /* anula o padding global de .tab-content (custom.css) */
    .cfg-content > form > .tab-pane { display: none; }
    .cfg-content > form > .tab-pane.active { display: block; }
    .cfg-card { padding: 1.75rem; }
    .cfg-head { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding-bottom: 1.25rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--dash-line); }
    .cfg-head-icon, .cfg-row-icon { width: 48px; height: 48px; border-radius: 14px; background: var(--dash-primary-soft); color: var(--dash-primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
    .cfg-head-body { flex: 1 1 260px; min-width: 0; }
    .cfg-head-title { font-size: 1.4rem; font-weight: 700; color: var(--dash-ink); margin: 0; }
    .cfg-head-sub { color: var(--dash-muted); margin: .15rem 0 0; }
    .cfg-head-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
    .cfg-head-actions .btn, .cfg-head-actions .dash-btn-soft { white-space: nowrap; }

    .cfg-section + .cfg-section { margin-top: 1.75rem; }
    .cfg-section-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: .5rem; margin-bottom: .85rem; }
    .cfg-section-title { font-size: 1.05rem; font-weight: 700; color: var(--dash-ink); margin: 0; }
    .cfg-section-sub { display: block; color: var(--dash-muted); font-size: .95rem; }
    .cfg-card .form-label, .cfg-card label.cfg-label { font-weight: 600; color: var(--dash-ink); }
    .cfg-card .form-control, .cfg-card .form-select { border-radius: 12px; }
    .cfg-card .form-control:focus, .cfg-card .form-select:focus { border-color: var(--dash-primary); box-shadow: 0 0 0 .2rem var(--dash-primary-soft); }
    .cfg-hint { display: block; color: var(--dash-muted); font-size: .9rem; margin-top: .35rem; }
    /* Opções em botões (radios): partial duration-options — estilos .cfg-choice em components/css-dash */
    .cfg-card .form-switch .form-check-input { width: 2.6em; height: 1.4em; cursor: pointer; }

    /* Linhas de horário (dias da semana e pausas) */
    .cfg-slots { display: grid; gap: .5rem; }
    .cfg-slot { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1rem; padding: .65rem 1rem; border: 1px solid var(--dash-line); border-radius: 14px; background: #fbfcfd; transition: background .2s, border-color .2s; }
    .cfg-slot:hover { border-color: #dfe5ec; }
    .cfg-slot.is-flash { background: var(--dash-primary-soft); }
    .cfg-slot-toggle { display: flex; align-items: center; gap: .75rem; min-width: 190px; margin: 0; padding: 0; }
    .cfg-slot-toggle .form-check-input { margin: 0; float: none; }
    .cfg-slot-label { font-weight: 600; color: var(--dash-ink); cursor: pointer; margin: 0; }
    .cfg-slot-label i { color: var(--dash-primary); width: 1.1rem; text-align: center; margin-right: .25rem; }
    .cfg-slot-times { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .cfg-slot-times > span { color: var(--dash-muted); }
    .cfg-card .cfg-time { width: 120px; height: 42px !important; font-size: 1.15rem; border-radius: 10px; } /* altura global é 55px (custom.css) */
    .cfg-card textarea.form-control { height: auto !important; }
    .cfg-slot-times .invalid-feedback, .cfg-item-hours .invalid-feedback { flex-basis: 100%; margin-top: 0; }
    .cfg-slot-off { display: none; }
    .cfg-slot-end { margin-left: auto; display: flex; align-items: center; gap: .5rem; }
    .cfg-slot.is-off { background: #f6f8fa; }
    .cfg-slot.is-off .cfg-slot-label { color: var(--dash-muted); }
    .cfg-slot.is-off .cfg-slot-times, .cfg-slot.is-off .cfg-slot-copy { display: none; }
    .cfg-slot.is-off .cfg-slot-off { display: inline-flex; }
    .cfg-slot-copy { width: 36px; height: 36px; font-size: .95rem; }
    .cfg-day-mode { width: auto; min-width: 120px; }
    .cfg-day-mode[hidden] { display: none; }
    /* Períodos do dia: com "online e presencial", cada dia pode ter até 3 faixas, cada uma com seu formato */
    .cfg-periods { flex-direction: column; align-items: flex-start; }
    .cfg-period { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .cfg-period > span { color: var(--dash-muted); }
    .cfg-period[hidden], .cfg-period-add[hidden] { display: none; }
    .cfg-period-remove { width: 32px; height: 32px; font-size: .85rem; }

    /* Itens editáveis (feriados e serviços) */
    .cfg-items { display: grid; gap: .75rem; }
    .cfg-items > *, .cfg-slots > * { min-width: 0; }
    .cfg-card .dash-chip { white-space: nowrap; }
    .cfg-item { position: relative; padding: 1rem; border: 1px solid var(--dash-line); border-radius: 16px; background: #fbfcfd; }
    .cfg-item.is-new { border-color: #f1d48a; background: #fffcf3; }
    .cfg-item-new { display: none; }
    .cfg-item.is-new .cfg-item-new { display: inline-flex; }
    .cfg-item-hours { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: .85rem; padding-top: .85rem; border-top: 1px dashed #e3e8ee; color: var(--dash-muted); }
    .cfg-item-remove { width: 40px; height: 40px; background: #fdeaea; color: #d64545; border: 0; }
    .cfg-item-remove:hover, .cfg-item-remove:focus { background: #d64545; color: #fff; }

    /* Linhas de preferência (switch à direita) e integrações */
    .cfg-row { display: flex; align-items: flex-start; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid var(--dash-line); }
    .cfg-row:last-of-type { border-bottom: 0; }
    .cfg-row-body { flex: 1; min-width: 0; }
    .cfg-row-title { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: 1.05rem; font-weight: 600; color: var(--dash-ink); margin: 0; }
    .cfg-row-detail { display: block; color: var(--dash-muted); margin-top: .2rem; }
    .cfg-row .form-switch { margin: 0; padding-top: .2rem; }
    .cfg-integration { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 1.1rem; border: 1px solid var(--dash-line); border-radius: 16px; background: #fbfcfd; }
    .cfg-integration .cfg-row-body { flex: 1 1 240px; }
    .cfg-integration-actions .btn { border-radius: 12px; font-weight: 600; white-space: nowrap; }
    .cfg-benefits { list-style: none; padding: 0; margin: 1.25rem 0 0; display: grid; gap: .6rem; }
    .cfg-benefits li { display: flex; align-items: flex-start; gap: .6rem; color: var(--dash-ink); }
    .cfg-benefits i { color: #1c8a56; margin-top: .25rem; }
    .cfg-fees { margin-top: 1.5rem; }
    .cfg-fees .cfg-hint { display: block; }
    .cfg-fees-list { list-style: none; padding: 0; margin: .5rem 0 .75rem; }
    .cfg-fees-list li { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; padding: .5rem 0; border-bottom: 1px solid rgba(0, 0, 0, .08); color: var(--dash-ink); }

    /* Barra de ações (fixa no rodapé do painel) */
    .cfg-actions { position: sticky; bottom: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .5rem; margin: 1.75rem -1.75rem -1.75rem; padding: 1rem 1.75rem; background: rgba(255, 255, 255, .96); border-top: 1px solid var(--dash-line); border-radius: 0 0 var(--dash-radius) var(--dash-radius); }
    .cfg-actions .btn { border-radius: 12px; padding: .6rem 1.25rem; font-weight: 600; min-width: 180px; }
    .cfg-dirty-hint { display: none; align-items: center; gap: .4rem; color: #b27a00; font-weight: 600; margin-right: auto; }
    .cfg-actions.is-dirty .cfg-dirty-hint { display: inline-flex; }

    .cfg-photo { padding: 1rem; border: 1px solid var(--dash-line); border-radius: 16px; background: #fbfcfd; }
    .cfg-link-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
    .cfg-link-actions .dash-btn-soft { text-decoration: none; }

    .form-select-43{ height: 43px !important;}

    @media (max-width: 991.98px) {
        .cfg-nav { position: static; flex-direction: row; gap: .35rem; overflow-x: auto; padding: .5rem; }
        .cfg-nav-group { display: none; }
        .cfg-nav-items { flex-direction: row; gap: .35rem; }
        .cfg-nav .cfg-nav-link { white-space: nowrap; width: auto; }
    }
    @media (max-width: 767.98px) {
        .cfg-card { padding: 1.25rem; }
        .cfg-actions { margin: 1.25rem -1.25rem -1.25rem; padding: .85rem 1.25rem; }
        .cfg-actions .btn { flex: 1 1 auto; }
        .cfg-dirty-hint { width: 100%; }
        /* Linha 1: switch + dia + ação; linha 2: horários lado a lado */
        .cfg-slot { gap: .6rem; }
        .cfg-slot-toggle { min-width: 0; flex: 1 1 auto; }
        .cfg-slot-end { margin-left: auto; }
        .cfg-slot-times { order: 3; width: 100%; flex-wrap: nowrap; }
        .cfg-card .cfg-slot-times .cfg-time { flex: 1 1 0; width: auto; min-width: 0; }
        .cfg-periods { flex-wrap: nowrap; }
        .cfg-period { width: 100%; }
        .cfg-item-hours .cfg-time { flex: 1 1 100px; }
        .cfg-head-actions { width: 100%; }
        .cfg-head-actions > * { flex: 1 1 auto; }
        .cfg-integration-actions { width: 100%; }
        .cfg-integration-actions .btn { width: 100%; }
    }
</style>
