{{-- Página pública da escala: sem dado pessoal, sem scripts ou fontes de terceiros (a URL carrega o token). --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#2F6F6A">
<title>@yield('title', 'Questionário')</title>
<link rel="icon" href="{{ asset('favicon.ico') }}">
<link rel="stylesheet" href="{{ mix('css/public-scale.css') }}">
</head>
<body>
<main class="ps-shell">
    @yield('content')
</main>
<footer class="ps-footer">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm0 2.2 6 2.2V11c0 4-2.6 7.8-6 8.9-3.4-1.1-6-4.9-6-8.9V6.4l6-2.2Z"/></svg>
    Página protegida · respostas cifradas no seu aparelho
</footer>
@yield('footer')
</body>
</html>
