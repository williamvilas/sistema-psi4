<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} · Sistema para psicólogas(os) com prontuário cifrado de ponta a ponta</title>
    <meta name="description" content="O que você escreve no prontuário é cifrado no seu navegador, com uma chave que só você tem. Nem nós conseguimos ler. E a agenda, as mensagens, a cobrança, os recibos do Receita Saúde, as declarações e as dinâmicas na sessão online ficam mais leves. Plano grátis para sempre, sem cartão.">
    <meta name="theme-color" content="#f5f9f9">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('imgs/logos/logo-psi4.ico') }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:title" content="{{ config('app.name') }} · Nem nós conseguimos ler o que você escreve no prontuário.">
    <meta property="og:description" content="Nem nós conseguimos ler o que você escreve no prontuário. Agenda, sala de vídeo com dinâmicas, cobrança, Receita Saúde e documentos para psicólogas(os).">
    <meta property="og:image" content="{{ asset('imgs/landing-page/og-psi4-v2.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..600;1,9..144,400&family=Inter:wght@400;500;600&family=Sora:wght@700&display=swap">
    <link rel="stylesheet" href="{{ mix('css/lp.css') }}">
</head>
<body class="lp" data-funnel-url="{{ route('funnel.track') }}">

@php
    $appName = config('app.name');
    // Com cadastro só por convite (REGISTRATION_INVITE_ONLY), o CTA leva ao pedido de convite.
    $freePlan = config('plans.plans.free');
    $inviteOnly = (bool) config('registration.invite_only');
    $linkRegister = $inviteOnly ? route('invite.request') : route('register');
    $ctaRegister = $inviteOnly ? 'Quero meu convite' : 'Criar conta grátis';
    $linkLogin = route('login');
    $linkTerms = route('terms-service');
    $linkPrivacy = route('privacy-policy');
    $linkSecurity = route('security');

    // Planos reais (config/plans.php). O plano Profissional é o único plano pago.
    $proPlan = config('plans.plans.professional');
    $proName = __('plans.professional');
    // Valores redondos sem centavos (R$ 49), os demais com (R$ 421,20)
    $money = fn (float $value) => number_format($value, fmod($value, 1.0) == 0.0 ? 0 : 2, ',', '.');
    $freeFee = $money((float) config('plans.plans.free.payment_fee_percent'));
    $proPrice = $money((float) $proPlan['price']);
    $annual = \App\Helpers\PlanHelper::annualConfig('professional');
    $annualMonthly = $money(\App\Helpers\PlanHelper::annualMonthlyEquivalent('professional'));
    $annualTotal = $money(\App\Helpers\PlanHelper::annualTotal('professional'));
    $annualPix = $money(\App\Helpers\PlanHelper::annualPixTotal('professional'));
    $annualFull = $money((float) $proPlan['price'] * 12);
    $annualDiscount = \App\Helpers\PlanHelper::annualDiscount('professional');
    $annualInstallments = \App\Helpers\PlanHelper::annualInstallments('professional');

    // Transcrição da sessão: só aparece na landing com a função ligada (PSI_TRANSCRIPTION_ENABLED)
    $transcriptionEnabled = (bool) config('session_transcription.enabled');
    $freeTranscription = (int) config('plans.plans.free.total_transcription_minutes', 0);
    $proTranscription = (int) $proPlan['total_transcription_minutes'];

    $supportSchedule = app(\App\Services\SupportHoursService::class)->schedule();
    $supportLink = generateLinkWhatsApp('Olá! Tenho uma dúvida sobre o ' . $appName . '.');

    $navLinks = [
        '#como-funciona' => 'Como funciona',
        '#precos' => 'Preços',
        '#duvidas' => 'Dúvidas',
    ];
@endphp

<a href="#conteudo" class="lp-skip">Pular para o conteúdo</a>

@include('landing.sections.navbar')

<main id="conteudo">
    @include('landing.sections.hero')
    @include('landing.sections.journey')
    @include('landing.sections.video')
    @if($transcriptionEnabled)
        @include('landing.sections.transcription')
    @endif
    @include('landing.sections.plans')
    @include('landing.sections.faq')
    @include('landing.sections.cta')
</main>

@include('landing.sections.footer')

{{-- CTA fixo no mobile: aparece depois do hero --}}
<div id="mobile-cta" class="lp-mobile-cta is-hidden" aria-hidden="true">
    <a href="{{ $linkRegister }}" class="lp-btn lp-btn-primary" data-funnel="landing_cta_register" tabindex="-1">{{ $ctaRegister }}</a>
    <a href="{{ $supportLink }}" target="_blank" rel="noopener" class="lp-whatsapp" aria-label="Falar com uma pessoa pelo WhatsApp" tabindex="-1">
        <i class="bi bi-whatsapp" aria-hidden="true"></i>
    </a>
</div>

<a href="{{ $supportLink }}" target="_blank" rel="noopener" class="lp-whatsapp lp-whatsapp-float" aria-label="Falar com uma pessoa pelo WhatsApp">
    <i class="bi bi-whatsapp" aria-hidden="true"></i>
</a>

<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => $appName,
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Web',
    'inLanguage' => 'pt-BR',
    'url' => url('/'),
    'description' => 'Sistema para psicólogas e psicólogos com prontuário cifrado de ponta a ponta no navegador, agenda, sala de vídeo com dinâmicas para a sessão, cobrança, recibos do Receita Saúde e documentos.',
    'offers' => [
        ['@type' => 'Offer', 'name' => __('plans.free'), 'description' => 'Grátis para sempre, sem cartão: agenda, pacientes, prontuários cifrados, vídeochamadas com dinâmicas, relatórios e recibos do Receita Saúde, tudo ilimitado. Taxa de ' . config('plans.plans.free.payment_fee_percent') . '% sobre cobranças pagas pelo Mercado Pago.', 'price' => '0', 'priceCurrency' => 'BRL'],
        ['@type' => 'Offer', 'name' => $proName . ' mensal', 'description' => 'Tudo do Grátis, com emissão de documentos, avisos automáticos por WhatsApp e sem taxa do PSI4 sobre cobranças pagas (a taxa do Mercado Pago continua).', 'price' => (string) $proPlan['price'], 'priceCurrency' => 'BRL'],
        ...($annual ? [['@type' => 'Offer', 'name' => $proName . ' anual', 'price' => (string) \App\Helpers\PlanHelper::annualTotal('professional'), 'priceCurrency' => 'BRL']] : []),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>

<script src="{{ mix('js/lp.js') }}" defer></script>
<x-funnel-tag tag="landing_page_v5" />
</body>
</html>
