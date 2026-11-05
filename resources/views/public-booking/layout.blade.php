<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title')</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="{{ mix('css/dialog.css') }}" rel="stylesheet">

<style>
  :root{
    --brand:#2F6F6A;
    --brand-dark:#245551;
    --brand-light:#EAF3F1;
    --muted:#6B7C7B;
    --line:#E3EAE9;
  }
  body{
    font-family:'Inter',system-ui,sans-serif;
    background:#F7FAF9;
    color:#16292A;
  }
  .card-main{
    background:#fff;
    border-radius:24px;
    box-shadow:0 20px 50px rgba(16,60,55,.08);
    overflow:hidden;
    max-width:960px;
    margin:40px auto;
  }
  .photo-side{
    background:linear-gradient(160deg,#2F6F6A 0%,#245551 100%);
    padding:40px 30px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    text-align:center;
    color:#fff;
    height:100%;
  }
  .photo-side img{
    width:170px;
    height:170px;
    object-fit:cover;
    border-radius:50%;
    border:5px solid rgba(255,255,255,.25);
    margin-bottom:20px;
    box-shadow:0 10px 30px rgba(0,0,0,.2);
  }
  .photo-side h1{
    font-size:1.4rem;
    font-weight:700;
    margin-bottom:4px;
  }
  .photo-side .crp{
    font-size:.85rem;
    opacity:.85;
    margin-bottom:20px;
  }
  .photo-side .info{
    font-size:.85rem;
    opacity:.9;
    line-height:1.7;
  }
  .photo-side .info i{
    margin-right:6px;
  }
  .photo-side .info-label{
    display:block;
    font-weight:600;
    font-size:.75rem;
    letter-spacing:.04em;
    text-transform:uppercase;
    opacity:.8;
    margin-bottom:2px;
  }
  .photo-side .maps-link{
    color:#fff;
    text-decoration:none;
    display:inline-block;
    border-radius:8px;
    padding:2px 6px;
  }
  .photo-side .maps-link:hover .maps-cta,
  .photo-side .maps-link:focus-visible .maps-cta{
    text-decoration:underline;
  }
  .photo-side .maps-link:focus-visible{
    outline:2px solid #fff;
    outline-offset:2px;
  }
  .photo-side .maps-cta{
    display:block;
    margin-top:4px;
    font-weight:600;
    text-decoration:underline;
    text-underline-offset:3px;
  }
  .photo-side .maps-cta i{
    margin:0 0 0 4px;
    font-size:.75rem;
  }
  .form-side{
    padding:40px;
  }
  @media (max-width:767.98px){
    .form-side{padding:28px 22px;}
    .photo-side{padding:32px 22px;}
    .card-main{margin:16px;}
  }
  h2.title{
    font-size:1.3rem;
    font-weight:700;
    margin-bottom:6px;
  }
  .subtitle{
    font-size:.9rem;
    color:var(--muted);
    margin-bottom:26px;
  }
  .form-label{
    font-size:.85rem;
    font-weight:600;
    margin-bottom:6px;
  }
  .form-control, .form-select{
    border-radius:10px;
    border-color:#DCE6E5;
    padding:.6rem .85rem;
    font-size:.92rem;
  }
  .form-control:focus, .form-select:focus{
    border-color:var(--brand);
    box-shadow:0 0 0 3px rgba(47,111,106,.13);
  }
  .btn-brand{
    background:var(--brand);
    border:none;
    color:#fff;
    font-weight:600;
    border-radius:10px;
    padding:.7rem 1.2rem;
    transition:.2s;
  }
  .btn-brand:hover{
    background:var(--brand-dark);
    color:#fff;
  }
  .hora-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:8px;
  }
  @media (max-width:575.98px){
    .hora-grid{grid-template-columns:repeat(3,1fr);}
  }
  .hora-btn{
    border:1.5px solid var(--line);
    background:#fff;
    border-radius:9px;
    padding:.5rem .2rem;
    font-size:.85rem;
    font-weight:600;
    color:#3C4F4E;
    transition:.15s;
  }
  .hora-btn:hover:not(:disabled){
    border-color:var(--brand);
    background:var(--brand-light);
    color:var(--brand);
  }
  .hora-btn.active{
    background:var(--brand);
    border-color:var(--brand);
    color:#fff;
  }
  .hora-btn:disabled{
    opacity:.4;
    cursor:not-allowed;
    text-decoration:line-through;
  }
  .dias-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:8px;
  }
  @media (max-width:575.98px){
    .dias-grid{grid-template-columns:repeat(3,1fr);}
  }
  .dia-btn{
    border:1.5px solid var(--line);
    background:#fff;
    border-radius:12px;
    padding:.55rem .3rem;
    display:flex;
    flex-direction:column;
    align-items:center;
    transition:.15s;
  }
  .dia-btn .dw{font-size:.7rem;text-transform:uppercase;font-weight:600;color:var(--muted);}
  .dia-btn .dd{font-size:1.1rem;font-weight:700;line-height:1.2;}
  .dia-btn .dm{font-size:.68rem;text-transform:uppercase;color:var(--muted);font-weight:600;}
  .dia-btn:hover{
    border-color:var(--brand);
    background:var(--brand-light);
  }
  .dia-btn.active{
    background:var(--brand);
    border-color:var(--brand);
  }
  .dia-btn.active .dw,
  .dia-btn.active .dd,
  .dia-btn.active .dm{color:#fff;}
  .section-label{
    font-size:.85rem;
    font-weight:700;
    color:#3C4F4E;
    margin-bottom:10px;
    display:flex;
    align-items:center;
    gap:8px;
  }
  .section-label .num{
    width:22px;height:22px;
    background:var(--brand);
    color:#fff;
    border-radius:50%;
    display:grid;place-items:center;
    font-size:.72rem;
    flex:0 0 22px;
  }
  .hint{
    font-size:.82rem;
    color:var(--muted);
    padding:14px;
    text-align:center;
    border:1.5px dashed #D6E3E1;
    border-radius:10px;
    background:#FCFEFD;
  }
  hr.divider{
    border-color:var(--line);
    opacity:1;
    margin:24px 0;
  }
  /* ============ WIZARD ============ */
  .wizard-progress{display:flex;gap:6px;margin-bottom:26px;}
  .wizard-progress span{flex:1;height:5px;border-radius:4px;background:var(--line);transition:.3s;}
  .wizard-progress span.done{background:var(--brand);}
  .step{display:none;}
  .step.active{display:block;animation:stepIn .3s ease;}
  @keyframes stepIn{from{opacity:0;transform:translateX(16px);}to{opacity:1;transform:none;}}
  .step-title{font-size:1.05rem;font-weight:700;margin-bottom:14px;}
  .opt-list{display:grid;gap:10px;}
  .opt-btn{display:flex;align-items:center;gap:14px;text-align:left;border:1.5px solid var(--line);background:#fff;border-radius:14px;padding:14px 16px;transition:.15s;}
  .opt-btn i{font-size:1.4rem;color:var(--brand);}
  .opt-btn strong{display:block;font-size:.95rem;}
  .opt-btn small{color:var(--muted);font-size:.8rem;}
  .opt-btn:hover,.opt-btn.active{border-color:var(--brand);background:var(--brand-light);}
  .horarios-wrap,.modalidade-wrap{margin-top:22px;}
  .modalidade-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;}
  .modalidade-btn i{margin-right:.35rem;}
  .modalidade-btn:disabled{text-decoration:none;}
  .modalidade-hint{font-size:.78rem;color:var(--muted);margin-top:8px;}
  .modalidade-tag{font-weight:600;color:var(--brand);}
  .modalidade-tag i{margin-right:.25rem;}
  .modalidade-tag:not(:empty)::before{content:'·';color:var(--muted);margin-right:8px;}
  .wizard-nav{display:flex;align-items:center;gap:10px;margin-top:22px;}
  .btn-back{background:none;border:none;color:var(--muted);font-size:.88rem;font-weight:600;padding:.5rem .2rem;}
  .btn-back:hover{color:var(--brand);}
  .btn-back[hidden]{display:none;}
  .resumo{font-size:.82rem;color:var(--muted);margin-bottom:16px;min-height:1.2em;}
  .resumo strong{color:var(--brand-dark);}
</style>
@yield('head')
</head>
<body>

<div class="container">
  <div class="card-main">
    <div class="row g-0">
      <div class="col-md-5">
        @include('public-booking.partials.photo-side', ['profile' => $profile])
      </div>
      <div class="col-md-7">
        @yield('content')
      </div>
    </div>
  </div>
</div>

@yield('footer')
</body>
</html>
