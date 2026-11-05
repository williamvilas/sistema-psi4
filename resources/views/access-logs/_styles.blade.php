{{-- Itens da atividade de acesso (tela completa e painel lateral do prontuário). --}}
<style>
    .al-item { display: flex; gap: 1rem; align-items: flex-start; padding: 1rem 1.25rem; border-bottom: 1px solid var(--dash-line, #edf1f5); }
    .al-item:last-child { border-bottom: 0; }
    .al-icon {
        width: 40px; height: 40px; border-radius: 12px; background: var(--dash-primary-soft, #e3f1f2); color: var(--dash-primary, #1f7a80);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .al-icon.is-danger { background: #fdeaea; color: #d64545; }
    .al-body { min-width: 0; flex: 1; }
    .al-action { font-weight: 600; color: var(--dash-ink, #1e2a3e); }
    .al-record { color: var(--dash-ink, #1e2a3e); }
    .al-meta { color: var(--dash-muted, #6b7a90); font-size: .95rem; }
    .al-time { color: var(--dash-muted, #6b7a90); font-size: .95rem; white-space: nowrap; }
    .al-item-compact { gap: .75rem; padding: .75rem 1rem; }
    .al-item-compact .al-icon { width: 34px; height: 34px; border-radius: 10px; font-size: .9rem; }
    .al-item-compact .al-meta { font-size: .85rem; overflow-wrap: anywhere; }
</style>
