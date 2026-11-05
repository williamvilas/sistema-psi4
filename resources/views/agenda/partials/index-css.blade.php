<style>
    /* Agenda: lista agrupada por dia, horário em destaque e ações por estado da sessão. Usa os tokens de components.css-dash. */
    .agenda-day { display: flex; align-items: baseline; gap: .6rem; margin: .5rem 0 1rem; color: var(--dash-ink); }
    .agenda-day-label { font-size: 1.3rem; font-weight: 700; }
    .agenda-day-date { font-size: 1.3rem; color: var(--dash-muted); }
    .agenda-day.is-today .agenda-day-label { color: var(--dash-primary); }

    .agenda-card { position: relative; }
    .agenda-card.is-now { box-shadow: 0 0 0 2px var(--dash-primary), var(--dash-shadow); }
    .agenda-card.is-pending { box-shadow: 0 0 0 1px #f5e3b5, var(--dash-shadow); }
    .agenda-card.is-closed .agenda-time,
    .agenda-card.is-closed .dash-item-name { opacity: .65; }

    .agenda-time { min-width: 64px; padding: .45rem .35rem; border-radius: 14px; background: var(--dash-primary-soft); color: var(--dash-primary); text-align: center; line-height: 1.15; flex-shrink: 0; }
    .agenda-time-start { display: block; font-size: 1.3rem; font-weight: 700; }
    .agenda-time-end { display: block; font-size: 1.10rem; opacity: .8; }

    .agenda-meta { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; margin: 1rem 0; color: var(--dash-muted); }
    .agenda-meta-item { display: inline-flex; align-items: center; gap: .4rem; min-width: 0;     font-size: 1.3rem; }
    .agenda-hint { display: inline-flex; align-items: center; gap: .35rem; font-size: .9rem; font-weight: 600; }
    .agenda-hint-now { color: var(--dash-primary); }
    .agenda-hint-now::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--dash-primary); animation: agenda-pulse 1.4s infinite; }
    .agenda-hint-soon { color: #b27a00; }
    .agenda-hint-pending { color: #b27a00; }

    .agenda-actions .btn { display: inline-flex; align-items: center; min-height: 40px; padding: .5rem .9rem; border-radius: 12px; font-weight: 600; }
    .agenda-btn-success { background: #e4f6ec; color: #1c8a56; font-weight: 600; border: 0; border-radius: 12px; padding: .5rem .9rem; }
    .agenda-btn-success:hover, .agenda-btn-success:focus { background: #1c8a56; color: #fff; }
    .agenda-btn-danger { background: #fdeaea; color: #d64545; font-weight: 600; border: 0; border-radius: 12px; padding: .5rem .9rem; }
    .agenda-btn-danger:hover, .agenda-btn-danger:focus { background: #d64545; color: #fff; }
    .agenda-actions .btn:disabled { opacity: 1; box-shadow: inset 0 0 0 2px currentColor; }
    .dash-round-btn.dash-round-btn-success .fa-whatsapp { font-size: 1.9rem; }

    /* Link da página pública de agendamento: endereço + copiar/compartilhar/abrir */
    .agenda-share { display: flex; align-items: stretch; min-height: 100%; background: #fff; border: 1px solid #dfe5ec; border-radius: 12px; overflow: hidden; }
    .agenda-share-url { display: flex; align-items: center; gap: .5rem; min-width: 0; max-width: 360px; padding: .5rem 1rem; color: var(--dash-muted); font-weight: 600; font-size: 1.1rem; }
    .agenda-share-url .fa-solid { color: var(--dash-primary); flex-shrink: 0; }
    .agenda-share-actions { display: flex; flex-shrink: 0; }
    .agenda-share-actions .btn { display: inline-flex; align-items: center; justify-content: center; border-radius: 0; border-width: 0 0 0 1px; white-space: nowrap; }
    .agenda-share-copy { min-width: 150px; }
    .agenda-share-copy.is-copied, .agenda-share-copy.is-copied:hover { background: #1c8a56; border-color: #1c8a56; color: #fff; }
    .agenda-share.is-off .agenda-share-url .fa-solid { color: var(--dash-muted); }
    .agenda-share-hint { display: inline-block; margin-top: .4rem; font-size: .9rem; font-weight: 600; color: var(--dash-muted); text-decoration: none; }
    .agenda-share-hint:hover { color: var(--dash-primary); }
    @media (max-width: 575.98px) {
        .agenda-share { flex-direction: column; }
        .agenda-share-url { max-width: none; border-bottom: 1px solid #dfe5ec; }
        .agenda-share-actions .btn { flex: 1; border-width: 0; }
        .agenda-share-actions .btn + .btn { border-left-width: 1px; }
        .agenda-share-copy { min-width: 0; }
        .agenda-share-open { flex: 0 0 56px !important; }
    }

    @keyframes agenda-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }
    @media (prefers-reduced-motion: reduce) { .agenda-hint-now::before { animation: none; } }
</style>
