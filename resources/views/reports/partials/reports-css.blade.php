@include('components.css-dash')
<style>
    .report-filter .form-control { border-radius: 12px; }
    .report-filter label.dash-eyebrow { display: block; margin-bottom: .35rem; }
    .report-skeleton-tiles .dash-skeleton { height: 140px; margin-bottom: 0; }
    .report-kpi { height: 100%; display: flex; flex-direction: column; align-items: flex-start; }
    .report-kpi-head { display: flex; align-items: center; gap: .6rem; }
    .report-kpi-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .report-kpi .dash-kpi-value { margin: .6rem 0 .1rem; line-height: 1.1; }
    .report-kpi-count { color: var(--dash-muted); font-size: 1.1rem; }
    .report-kpi-compare { margin-top: auto; padding-top: .75rem; display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; font-size: 1rem; color: var(--dash-muted); }
    .report-kpi.is-hero { background: linear-gradient(135deg, #2a9a9f, var(--dash-primary)); color: #fff; }
    .report-kpi.is-hero .dash-eyebrow, .report-kpi.is-hero .report-kpi-count, .report-kpi.is-hero .report-kpi-compare { color: rgba(255, 255, 255, .85); }
    .report-kpi.is-hero .dash-kpi-value { color: #fff; }
    .report-kpi.is-hero .report-kpi-icon { background: rgba(255, 255, 255, .18); color: #fff; }
    .report-kpi.is-hero .dash-chip { background: rgba(255, 255, 255, .2); color: #fff; }
    .report-method { padding: .6rem 0; border-bottom: 1px solid var(--dash-line); }
    .report-method:last-child { border-bottom: 0; }
    .report-method-head { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; margin-bottom: .4rem; }
    .report-method-name { font-weight: 600; color: var(--dash-ink); font-size: 1.15rem; }
    .report-method-percent { font-weight: 700; color: var(--dash-primary); font-size: 1.15rem; }
    .report-method .dash-progress { margin-bottom: .3rem; }
    .report-method-detail { color: var(--dash-muted); font-size: 1rem; }
    .dash-progress.is-danger { background: #fdeaea; }
    .dash-progress.is-danger span { background: linear-gradient(90deg, #ec8a8a, #d64545); }
    .dash-progress.is-warning { background: #fff4d9; }
    .dash-progress.is-warning span { background: linear-gradient(90deg, #f3c35a, #d99a00); }
    .report-alert { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; padding: 1rem 1.25rem; border-radius: var(--dash-radius); background: #fff4d9; color: #7a5300; box-shadow: var(--dash-shadow); }
    .report-alert-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(178, 122, 0, .12); color: #b27a00; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
    .report-alert-body { flex: 1; min-width: 220px; display: flex; flex-direction: column; font-size: 1.1rem; }
    .report-alert-btn { background: #b27a00; color: #fff; border-radius: 12px; font-weight: 600; }
    .report-alert-btn:hover, .report-alert-btn:focus { background: #8f6200; color: #fff; }
    .report-insight { display: flex; gap: .6rem; align-items: flex-start; padding: .8rem 1rem; border-radius: 14px; background: var(--dash-primary-soft); color: var(--dash-ink); font-size: 1.1rem; font-weight: 600; }
    .report-insight i { color: var(--dash-primary); margin-top: .2rem; }
    .report-patient .dash-row-name:hover { color: var(--dash-primary); text-decoration: underline; }
    .report-patient-progress { height: 6px; margin: .35rem 0 0; max-width: 320px; }
    .report-patient-rate { text-align: right; flex-shrink: 0; display: flex; flex-direction: column; }
    .report-patient-rate strong { font-size: 1.3rem; color: var(--dash-ink); }
    .report-patient-rate small { color: var(--dash-muted); font-size: .95rem !important; }
    .report-kpi-progress { width: 100%; margin: .2rem 0 .5rem; }
    .report-kpi.is-hero .report-kpi-progress { background: rgba(255, 255, 255, .25); }
    .report-kpi.is-hero .report-kpi-progress span { background: #fff; }
    .report-kpi-link { margin-left: auto; font-weight: 600; color: var(--dash-primary); text-decoration: none; white-space: nowrap; }
    .report-kpi-link:hover { text-decoration: underline; color: var(--dash-primary); }
    .report-kpi-breakdown { gap: .4rem; }
    .report-alert.is-info { background: #e6effa; color: #22548a; }
    .report-alert.is-info .report-alert-icon { background: rgba(43, 108, 176, .12); color: #2b6cb0; }
    .report-alert.is-info .report-alert-btn { background: #2b6cb0; }
    .report-alert.is-info .report-alert-btn:hover, .report-alert.is-info .report-alert-btn:focus { background: #22548a; }
    .report-heatmap-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .report-heatmap { width: 100%; border-collapse: separate; border-spacing: 4px; min-width: 520px; }
    .report-heatmap th { color: var(--dash-muted); font-weight: 600; font-size: .95rem; text-align: center; white-space: nowrap; }
    .report-heatmap tbody th { text-align: left; padding-right: .5rem; color: var(--dash-ink); }
    .report-heatmap td { height: 38px; min-width: 42px; border-radius: 8px; text-align: center; font-size: .9rem; font-weight: 600; color: var(--dash-ink); background: rgba(31, 122, 128, var(--heat, .08)); cursor: default; }
    .report-heatmap td.is-strong { color: #fff; }
    .report-heatmap td.is-off { background: repeating-linear-gradient(45deg, #f6f8fa, #f6f8fa 4px, #edf1f5 4px, #edf1f5 8px); }
    .report-heatmap-legend { display: flex; align-items: center; gap: .5rem; color: var(--dash-muted); font-size: .95rem; }
    .report-heatmap-scale { width: 96px; height: 10px; border-radius: 6px; background: linear-gradient(90deg, rgba(31, 122, 128, .08), rgba(31, 122, 128, .92)); }
    .dash-empty .btn i { display: inline-block; font-size: inherit; margin-bottom: 0; color: inherit; }
</style>
