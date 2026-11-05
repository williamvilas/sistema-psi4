<style>
    :root {
        --dash-primary: #1f7a80;
        --dash-primary-soft: #e3f1f2;
        --dash-ink: #1e2a3e;
        --dash-muted: #6b7a90;
        --dash-surface: #ffffff;
        --dash-line: #edf1f5;
        --dash-radius: 20px;
        --dash-shadow: 0 8px 24px rgba(30, 42, 62, 0.06), 0 1px 3px rgba(30, 42, 62, 0.05);
    }
    .dash-empty p {font-size: 1.3rem;}
    .dash-card, .dash-tile { font-size: 1rem; }
    .dash-grid { display: grid; gap: 1rem; }
    .dash-tiles, .dash-shortcuts { grid-template-columns: repeat(2, 1fr); }
    @media (min-width: 992px) {
        .dash-tiles, .dash-shortcuts { grid-template-columns: repeat(4, 1fr); }
    }
    .dash-card, .dash-tile {
        background: var(--dash-surface);
        border-radius: var(--dash-radius);
        box-shadow: var(--dash-shadow);
        padding: 1.25rem;
    }
    .dash-tile {
        position: relative; display: flex; flex-direction: column; align-items: flex-start;
        text-decoration: none; color: var(--dash-ink); transition: transform .15s, box-shadow .2s;
    }
    .dash-tile:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(30, 42, 62, 0.1); color: var(--dash-ink); }
    .dash-tile.is-empty { cursor: default; }
    .dash-tile.is-empty:hover { transform: none; box-shadow: var(--dash-shadow); }
    .dash-tile-icon {
        width: 56px; height: 56px; border-radius: 18px; background: var(--dash-primary-soft); color: var(--dash-primary);
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;
    }
    .dash-tile.is-alert .dash-tile-icon { background: #fdeaea; color: #d64545; }
    .dash-tile.is-alert .dash-tile-count { color: #d64545; }
    .dash-tile-count { font-size: 2.6rem; font-weight: 700; line-height: 1; }
    .dash-tile-label { font-weight: 600; font-size: 1.2rem; margin-top: .4rem; }
    .dash-tile-hint { color: var(--dash-muted); font-size: 1.2rem; margin-top: .3rem; }
    .dash-shortcut {
        display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; border-radius: 16px;
        background: var(--dash-primary-soft); color: var(--dash-primary); font-weight: 600; font-size: 1.2rem; text-decoration: none; transition: background .15s;
    }
    .dash-round-btn .fa-whatsapp {font-size: 2.0rem; margin-right: 0 !important;}
    .dash-shortcut:hover { background: var(--dash-primary); color: #fff; }
    .dash-shortcut-icon { width: 40px; height: 40px; border-radius: 12px; background: #fff; color: var(--dash-primary); display: flex; align-items: center; justify-content: center; }
    .dash-eyebrow { color: var(--dash-muted); text-transform: uppercase; font-size: 1.0rem; font-weight: 600; letter-spacing: .04em; }
    .dash-kpi-value { font-size: 2.5rem; font-weight: 700; color: var(--dash-ink); margin: .25rem 0 .5rem; }
    .dash-progress { height: 8px; border-radius: 8px; background: var(--dash-primary-soft); overflow: hidden; margin-bottom: .6rem; }
    .dash-progress span { display: block; height: 100%; border-radius: 8px; background: linear-gradient(90deg, #4cb5ba, var(--dash-primary)); }
    .dash-chip { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .7rem; border-radius: 20px; font-size: 0.98rem; font-weight: 600; }
    .dash-chip-success { background: #e4f6ec; color: #1c8a56; }
    .dash-chip-danger { background: #fdeaea; color: #d64545; }
    .dash-chip-warning { background: #fff4d9; color: #b27a00; }
    .dash-chip-info { background: #e6effa; color: #2b6cb0; }
    .dash-title { font-size: 1.4rem; font-weight: 700; color: var(--dash-ink); margin-bottom: 1rem; display: flex; align-items: center; gap: .5rem; }
    .dash-title i { color: var(--dash-primary); }
    .dash-title.is-alert i { color: #d64545; }
    .dash-count { margin-left: auto; background: #fdeaea; color: #d64545; border-radius: 20px; padding: 0 .7rem; font-size: .9rem; line-height: 1.7; }
    .dash-row { display: flex; align-items: center; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--dash-line); }
    .dash-row:last-of-type { border-bottom: 0; }
    .dash-row-body { flex: 1; min-width: 0; }
    .dash-row-name { display: block; font-size: 1.2rem; font-weight: 600; color: var(--dash-ink); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dash-row-detail { font-size: 1.2rem; color: var(--dash-muted); }
    .dash-time { min-width: 52px; text-align: center; padding: .35rem 0; border-radius: 12px; background: var(--dash-primary-soft); color: var(--dash-primary); font-weight: 700; font-size: 1rem; }
    .dash-avatar { width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; flex-shrink: 0; }
    .dash-tone-0 { background: #e3f1f2; color: #1f7a80; }
    .dash-tone-1 { background: #e6effa; color: #2b6cb0; }
    .dash-tone-2 { background: #fdeaea; color: #d64545; }
    .dash-tone-3 { background: #fff4d9; color: #b27a00; }
    .dash-tone-4 { background: #efe8fa; color: #6b46c1; }
    .dash-tone-5 { background: #e4f6ec; color: #1c8a56; }
    .dash-round-btn { width: 40px; height: 40px; font-size: 1.1rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; flex-shrink: 0; transition: background .15s, color .15s; }
    .dash-round-btn-success { background: #e4f6ec; color: #1c8a56; }
    .dash-round-btn-success:hover { background: #1c8a56; color: #fff; }
    .dash-round-btn-primary { background: var(--dash-primary-soft); color: var(--dash-primary); }
    .dash-round-btn-primary:hover { background: var(--dash-primary); color: #fff; }
    .dash-empty { text-align: center; color: var(--dash-muted); font-size: 1.2rem; padding: 5.0rem 0; }
    .dash-empty i { display: block; font-size: 2.8rem; margin-bottom: .5rem; color: #b8c9cc; }
    .dash-card small { font-size: 1.2rem; }
    .dash-more { display: inline-block; margin-top: .5rem; font-size: .95rem; font-weight: 600; color: var(--dash-primary); text-decoration: none; }
    .dash-skeleton { height: 120px; border-radius: var(--dash-radius); margin-bottom: 1rem; background: linear-gradient(90deg, #eef2f6 25%, #f7f9fb 50%, #eef2f6 75%); background-size: 200% 100%; animation: dash-shimmer 1.2s infinite; }
    .dash-filter-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
    .dash-filter-chip { border: 1px solid #dfe5ec; background: var(--dash-surface); color: var(--dash-muted); border-radius: 20px; padding: .35rem 1rem; font-weight: 600; transition: background .15s, color .15s, border-color .15s; font-size: 1.05rem;}
    .dash-filter-chip:hover { color: var(--dash-primary); border-color: var(--dash-primary); }
    .dash-filter-chip.active { background: var(--dash-primary); border-color: var(--dash-primary); color: #fff; }
    .dash-total { color: var(--dash-muted); }
    .dash-item { background: var(--dash-surface); border-radius: var(--dash-radius); box-shadow: var(--dash-shadow); padding: 1.25rem; display: flex; flex-direction: column; transition: box-shadow .2s; }
    .dash-item:hover { box-shadow: 0 14px 30px rgba(30, 42, 62, 0.1); }
    .dash-item-head { display: flex; align-items: center; gap: .75rem; }
    .dash-item-name { display: block; font-size: 1.3rem; font-weight: 700; color: var(--dash-ink); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dash-item-name:hover { color: var(--dash-primary); }
    .dash-item-sub { font-size: 1.2rem; color: var(--dash-muted); }
    .dash-item-actions { display: flex; align-items: center; gap: .5rem; margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--dash-line); }
    .dash-btn-soft { background: var(--dash-primary-soft); color: var(--dash-primary); font-weight: 600; border: 0; border-radius: 12px; padding: .5rem .9rem; }
    .dash-btn-soft:hover, .dash-btn-soft:focus { background: var(--dash-primary); color: #fff; }
    /* Opções em botões (radios .btn-check + .cfg-choice): configurações, vencimento da cobrança */
    .cfg-choices { display: flex; flex-wrap: wrap; gap: .5rem; }
    .cfg-choice { margin: 0; padding: .55rem 1rem; border: 1px solid #858585; border-radius: 12px; background: #fff; color: var(--dash-ink); font-weight: 600; cursor: pointer; user-select: none; transition: background .15s, border-color .15s, color .15s; }
    .cfg-choice:hover { border-color: var(--dash-primary); color: var(--dash-primary); }
    .btn-check:checked + .cfg-choice { background: var(--dash-primary); border-color: var(--dash-primary); color: #fff; }
    .btn-check:focus-visible + .cfg-choice { box-shadow: 0 0 0 .2rem var(--dash-primary-soft); }
    .dash-round-muted { background: #f2f4f7; color: var(--dash-muted); border: 0; }
    .dash-round-muted:hover, .dash-round-muted[aria-expanded="true"] { background: #e3e7ec; color: var(--dash-ink); }
    .dash-chip-muted { background: #eef1f4; color: var(--dash-muted); }
    .dash-item .dropdown-item { font-size: 1.2rem; }
    @keyframes dash-shimmer { to { background-position: -200% 0; } }
</style>
