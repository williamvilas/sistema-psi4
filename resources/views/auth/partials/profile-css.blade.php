<style>
    .prof-card { padding: 1.75rem; margin-bottom: 1.25rem; }
    /* Cabeçalho com foto/iniciais, plano e atalho para a foto */
    .prof-head { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
    .prof-head .dash-avatar, .prof-photo { width: 72px; height: 72px; font-size: 1.5rem; border-radius: 50%; flex-shrink: 0; }
    .prof-photo { object-fit: cover; box-shadow: 0 0 0 3px var(--dash-primary-soft); }
    .prof-head-body { flex: 1 1 220px; min-width: 0; }
    .prof-head-name { font-size: 1.5rem; font-weight: 700; color: var(--dash-ink); margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .prof-head-sub { color: var(--dash-muted); overflow-wrap: anywhere; }
    .prof-head-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; }
    .prof-head-link { text-decoration: none; white-space: nowrap; }
    /* Formulário */
    .prof-legend { color: var(--dash-muted); font-size: .95rem; margin: -.5rem 0 1rem; }
    .prof-form label { font-weight: 600; color: var(--dash-ink); }
    .prof-input { position: relative; }
    .prof-input > i { position: absolute; left: 1rem; top: 27.5px; transform: translateY(-50%); color: var(--dash-muted); pointer-events: none; font-size: 1.6rem; }
    .prof-input > i.prof-input-lock { left: auto; right: 1rem; }
    .prof-input .form-control { padding: 0 1rem 0 3.4rem; border-radius: 12px; } /* altura global de 55px (app.css) */
    .prof-input .form-control.is-invalid { padding-right: 2.6rem; background-position: right 1rem center; }
    .prof-input.is-locked .form-control { background: #f5f7fa; color: var(--dash-muted); cursor: not-allowed; padding-right: 2.6rem; }
    .prof-input .btn-toggle-password { position: absolute; right: .35rem; top: 27.5px; transform: translateY(-50%); border: 0; background: transparent; color: var(--dash-muted); padding: .4rem .6rem; }
    .prof-input .btn-toggle-password:hover, .prof-input .btn-toggle-password:focus-visible { color: var(--dash-primary); }
    .prof-input-password .form-control { padding-right: 3rem; }
    .prof-form .form-control:focus { border-color: var(--dash-primary); box-shadow: 0 0 0 .2rem var(--dash-primary-soft); }
    .prof-hint { display: block; color: var(--dash-muted); margin-top: .35rem; }
    .prof-hint-link { color: var(--dash-primary); font-weight: 600; text-decoration: none; margin-left: .25rem; white-space: nowrap; }
    .prof-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .5rem; padding-top: 1.25rem; margin-top: .5rem; border-top: 1px solid var(--dash-line); }
    .prof-actions .btn { border-radius: 12px; padding: .6rem 1.25rem; font-weight: 600; }
    .prof-actions .btn-primary { min-width: 180px; }
    .prof-actions .btn-primary:disabled { opacity: .55; }
    .prof-dirty-hint { color: #b27a00; font-weight: 600; margin-right: auto; }
    /* Segurança */
    .prof-security-row { padding: .9rem 0; }
    .prof-security-icon { width: 44px; height: 44px; border-radius: 14px; background: var(--dash-primary-soft); color: var(--dash-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .prof-security-name { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: 1.1rem; font-weight: 600; color: var(--dash-ink); }
    .prof-security-detail { display: block; color: var(--dash-muted); }
    .prof-security-row .dash-btn-soft { text-decoration: none; white-space: nowrap; }
    /* Requisitos da nova senha */
    .prof-rules { list-style: none; padding: 0; margin: .5rem 0 0; color: var(--dash-muted); }
    .prof-rules li { display: flex; align-items: center; gap: .4rem; }
    .prof-rules li.is-ok { color: #1c8a56; }
    @media (max-width: 767.98px) {
        .prof-card { padding: 1.25rem; }
        .prof-head-name { white-space: normal; }
        .prof-head-link { width: 100%; text-align: center; }
        .prof-actions .btn { flex: 1 1 auto; }
        .prof-dirty-hint { width: 100%; }
        .prof-security-row { flex-wrap: wrap; }
        .prof-security-row .dash-btn-soft { width: 100%; text-align: center; }
    }
</style>
