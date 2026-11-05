<style>
    /* Formulário de cobrança (criar/editar): seções numeradas + resumo ao vivo */
    .pay-form-card { padding: 1.75rem; }
    .pay-form-card fieldset[disabled] { opacity: .55; }
    .pay-section + .pay-section { margin-top: 1.75rem; padding-top: 1.75rem; border-top: 1px solid var(--dash-line); }
    .pay-section-head { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; }
    .pay-step { width: 30px; height: 30px; border-radius: 50%; background: var(--dash-primary-soft); color: var(--dash-primary); font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .pay-section-title { font-size: 1.20rem; font-weight: 700; color: var(--dash-ink); margin: 0; }
    .pay-label { font-weight: 600; color: var(--dash-ink); margin-bottom: .4rem; }
    .pay-optional { color: var(--dash-muted); font-weight: 400; font-size: .9rem; }
    .pay-hint { display: block; color: var(--dash-muted); font-size: .9rem; margin-top: .4rem; }
    .pay-form-card .form-control, .pay-form-card .form-select { border-radius: 12px; min-height: 46px; }
    .pay-form-card .form-control:focus { border-color: var(--dash-primary); box-shadow: 0 0 0 .2rem var(--dash-primary-soft); }
    .pay-form-card .select2-container .select2-selection--single { border-radius: 12px; }

    .pay-amount-input.form-control { font-size: 1.9rem; font-weight: 700; color: var(--dash-ink); min-height: 64px; max-width: 320px; }
    .pay-due-custom { max-width: 220px; margin-top: .75rem; }

    /* Formas de pagamento em cards selecionáveis */
    .pay-methods-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .75rem; }
    .pay-method { position: relative; display: flex; align-items: center; gap: .85rem; margin: 0; padding: 1rem 2.6rem 1rem 1rem; border: 1.5px solid #dfe5ec; border-radius: 16px; background: #fff; cursor: pointer; user-select: none; transition: border-color .15s, background .15s, box-shadow .15s; }
    .pay-method:hover { border-color: var(--dash-primary); }
    .pay-method-icon { width: 42px; height: 42px; border-radius: 12px; background: #f2f5f8; color: var(--dash-muted); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; transition: background .15s, color .15s; }
    .pay-method-body { display: flex; flex-direction: column; min-width: 0; }
    .pay-method-body strong { color: var(--dash-ink); }
    .pay-method-body small { color: var(--dash-muted); }
    .pay-method-check { position: absolute; top: 50%; right: 1rem; transform: translateY(-50%); font-size: 1.2rem; color: #c9d2dc; transition: color .15s; }
    .btn-check:checked + .pay-method { border-color: var(--dash-primary); background: var(--dash-primary-soft); }
    .btn-check:checked + .pay-method .pay-method-icon { background: var(--dash-primary); color: #fff; }
    .btn-check:checked + .pay-method .pay-method-check { color: var(--dash-primary); }
    .btn-check:focus-visible + .pay-method { box-shadow: 0 0 0 .2rem var(--dash-primary-soft); }

    /* Paciente fixo (edição) */
    .pay-patient { display: flex; align-items: center; gap: .85rem; padding: .85rem 1rem; border-radius: 16px; background: #fbfcfd; border: 1px solid var(--dash-line); }
    .pay-patient-body { flex: 1; min-width: 0; }
    .pay-patient-name { font-size: 1.20rem; font-weight: 700; color: var(--dash-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pay-patient-sub { color: var(--dash-muted); font-size: 1.10rem; }
    .pay-select-phone { color: var(--dash-muted); margin-left: .35rem; }

    /* Resumo ao vivo */
    .pay-preview { position: sticky; top: 1.5rem; padding: 1.5rem; }
    .pay-preview-patient { display: flex; align-items: center; gap: .5rem; margin-top: .75rem; color: var(--dash-ink); font-weight: 600; min-width: 0; }
    .pay-preview-patient span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pay-preview-patient.is-empty, .pay-preview-amount.is-empty { color: var(--dash-muted); }
    .pay-preview-amount { font-size: 2.4rem; font-weight: 700; color: var(--dash-ink); line-height: 1.1; margin: .5rem 0 1rem; word-break: break-word; }
    .pay-preview-list { list-style: none; padding: 1rem 0; margin: 0; border-top: 1px solid var(--dash-line); border-bottom: 1px solid var(--dash-line); display: grid; gap: .65rem; }
    .pay-preview-list li { display: flex; align-items: flex-start; gap: .65rem; color: var(--dash-ink); min-width: 0; }
    .pay-preview-list li i { width: 1.1rem; text-align: center; color: var(--dash-primary); margin-top: .2rem; }
    .pay-preview-list li span { min-width: 0; overflow-wrap: anywhere; }
    .pay-preview-note { display: flex; gap: .5rem; color: var(--dash-muted); font-size: 1.10rem; margin: 1rem 0; }
    .pay-preview-note i { margin-top: .2rem; font-size: 1.20rem;}
    .pay-preview .btn-primary { min-height: 48px; font-weight: 700; border-radius: 12px; }

    /* Rodapé fixo no celular com valor + ação principal */
    .pay-mobile-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1020; display: flex; align-items: center; gap: 1rem; padding: .75rem 1rem; background: #fff; box-shadow: 0 -6px 20px rgba(30, 42, 62, .08); }
    .pay-mobile-bar-amount { flex: 1; min-width: 0; font-weight: 700; font-size: 1.2rem; color: var(--dash-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pay-mobile-bar .btn { white-space: nowrap; font-weight: 700; border-radius: 12px; }
    @media (max-width: 991.98px) {
        .pay-form-page { padding-bottom: 5rem; }
        .pay-preview { position: static; }
    }
    @media (max-width: 575.98px) {
        .pay-form-card { padding: 1.25rem; }
        .pay-amount-input.form-control { max-width: none; }
    }

    /* Resumo somente leitura (cobrança que não pode mais ser editada) */
    .pay-readonly-row { display: flex; justify-content: space-between; gap: 1rem; padding: .7rem 0; border-bottom: 1px solid var(--dash-line); }
    .pay-readonly-row:last-of-type { border-bottom: 0; }
    .pay-readonly-row span:first-child { color: var(--dash-muted); }
    .pay-readonly-row span:last-child { font-weight: 600; color: var(--dash-ink); text-align: right; }
</style>
