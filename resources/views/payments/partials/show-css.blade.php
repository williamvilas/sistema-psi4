<style>
    /* Detalhes da cobrança: destaque do valor + estado da cobrança, paciente e ações na lateral */
    .pay-show-card { padding: 1.75rem; }
    .pay-show-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .pay-show-amount { font-size: 2.8rem; font-weight: 700; color: var(--dash-ink); line-height: 1.1; margin: .5rem 0 .75rem; word-break: break-word; }
    .pay-show-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.25rem; color: var(--dash-muted); }
    .pay-show-meta-item { display: inline-flex; align-items: center; min-width: 0; overflow-wrap: anywhere; }
    .pay-due-muted { color: var(--dash-muted); }
    .pay-due-warning { color: #b27a00; font-weight: 600; }
    .pay-due-danger { color: #d64545; font-weight: 600; }
    .pay-due-success { color: #1c8a56; }

    /* Bloco de estado (link, gerando, falha, pago, vencido...) */
    .pay-show-state { margin-top: 1.5rem; padding: 1.1rem 1.25rem; border-radius: 16px; background: #fbfcfd; border: 1px solid var(--dash-line); }
    .pay-show-state-title { display: flex; align-items: center; gap: .6rem; font-weight: 700; color: var(--dash-ink); margin-bottom: .25rem; }
    .pay-show-state-text { color: var(--dash-muted); margin: 0; }
    .pay-show-state.is-success { background: #f2fbf6; border-color: #cdeedb; }
    .pay-show-state.is-success .pay-show-state-title i { color: #1c8a56; }
    .pay-show-state.is-danger { background: #fef6f6; border-color: #f6d3d3; }
    .pay-show-state.is-danger .pay-show-state-title i { color: #d64545; }
    .pay-show-state.is-warning { background: #fffaf0; border-color: #f5e3b5; }
    .pay-show-state.is-warning .pay-show-state-title i { color: #b27a00; }
    .pay-show-state.is-info .pay-show-state-title i { color: #2b6cb0; }
    .pay-show-link-url { display: block; margin: 1.0rem 0rem 1.5rem 0rem; background: #fff; color: var(--dash-primary); font-size: 1.25rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none; }
    .pay-show-link-url:hover { border-color: var(--dash-primary); }
    .pay-show-link-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
    .pay-show-link-actions .btn { display: inline-flex; align-items: center; border-radius: 12px; font-weight: 600; min-height: 42px; }
    .pay-show-whatsapp { background: #e4f6ec; color: #1c8a56; border: 0; }
    .pay-show-whatsapp:hover, .pay-show-whatsapp:focus { background: #1c8a56; color: #fff; }
    .pay-show-whatsapp .fa-whatsapp { font-size: 1.25rem; }
    .dash-more{font-size:1.10rem;}

    /* Linhas de detalhe e histórico */
    .pay-show-section + .pay-show-section { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--dash-line); }
    .pay-show-row { font-size: 1.15rem;display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: .65rem 0; border-bottom: 1px solid var(--dash-line); }
    .pay-show-row:last-child { border-bottom: 0; }
    .pay-show-row > span:first-child { color: var(--dash-muted); flex-shrink: 0; }
    .pay-show-row > span:last-child { font-weight: 600; color: var(--dash-ink); text-align: right; min-width: 0; overflow-wrap: anywhere; }
    .pay-show-methods { display: inline-flex; flex-wrap: wrap; justify-content: flex-end; gap: .35rem .9rem; }
    .pay-show-empty { color: var(--dash-muted); font-weight: 400; }

    .pay-timeline { list-style: none; margin: .75rem 0 0; padding: 0; }
    .pay-timeline li { position: relative; display: flex; gap: .85rem; padding-bottom: 1rem; }
    .pay-timeline li:last-child { padding-bottom: 0; }
    .pay-timeline li:not(:last-child)::before { content: ''; position: absolute; left: 6px; top: 18px; bottom: 0; width: 2px; background: var(--dash-line); }
    .pay-timeline-dot { width: 14px; height: 14px; border-radius: 50%; margin-top: .3rem; flex-shrink: 0; background: #fff; border: 2px solid #c9d2dc; }
    .pay-timeline li.is-done .pay-timeline-dot { background: var(--dash-primary); border-color: var(--dash-primary); }
    .pay-timeline li.is-success .pay-timeline-dot { background: #1c8a56; border-color: #1c8a56; }
    .pay-timeline li.is-danger .pay-timeline-dot { background: #d64545; border-color: #d64545; }
    .pay-timeline-title { font-weight: 600; color: var(--dash-ink); }
    .pay-timeline li.is-next .pay-timeline-title { color: var(--dash-muted); font-weight: 400; }
    .pay-timeline-date { display: block; color: var(--dash-muted); font-size: 1.10rem; }

    /* Paciente */
    .pay-show-patient { display: flex; align-items: center; gap: .85rem; margin-top: .85rem; }
    .pay-show-patient-body { min-width: 0; }
    .pay-show-patient-name { display: block; font-weight: 700; font-size: 1.1rem; color: var(--dash-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pay-show-contacts { list-style: none; margin: 1rem 0 0; padding: 1rem 0 0; border-top: 1px solid var(--dash-line); display: grid; gap: .7rem; }
    .pay-show-contacts li { display: flex; align-items: center; gap: .65rem; color: var(--dash-ink); min-width: 0; }
    .pay-show-contacts li > i { width: 1.1rem; text-align: center; color: var(--dash-primary); flex-shrink: 0; }
    .pay-show-contacts li > span, .pay-show-contacts li > a { min-width: 0; overflow-wrap: anywhere; }
    .pay-show-contacts a { color: var(--dash-ink); text-decoration: none; }
    .pay-show-contacts a:hover { color: var(--dash-primary); }

    /* Ações */
    .pay-show-actions { display: grid; gap: .5rem; margin-top: .85rem; }
    .pay-show-actions .btn { border-radius: 12px; font-weight: 600; min-height: 44px; }
    .pay-show-delete { color: #d64545; background: transparent; border: 0; font-weight: 600; }
    .pay-show-delete:hover, .pay-show-delete:focus { color: #b02a2a; background: #fdeaea; }
    .pay-show-aside { position: sticky; top: 1.5rem; }

    @media (max-width: 991.98px) {
        .pay-show-aside { position: static; }
    }
    @media (max-width: 575.98px) {
        .pay-show-card { padding: 1.25rem; }
        .pay-show-amount { font-size: 2.2rem; }
        .pay-show-link-actions .btn { flex: 1 1 auto; justify-content: center; }
    }
</style>
