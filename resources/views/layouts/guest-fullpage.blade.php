<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login')</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('imgs/logos/psi4.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@700&display=swap" rel="stylesheet">
    <link href="{{ mix('css/app.css') }}" rel="stylesheet">
    <style>
        /* estilo suave – cores, transições; a página rola quando o conteúdo excede a tela */
        * {
            margin: 0;
            padding: 0;
        }
        body {
            background: linear-gradient(145deg, #f4f9fc 0%, #e6f0f5 100%);
            font-family: system-ui, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
        }
        .full-height-row {
            min-height: 100vh;
            min-height: 100dvh;
        }
        .form-col {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: flex;
            align-items: center;
            padding: 2rem 1.5rem;
            position: relative;
            min-width: 0;
        }
        .image-col {
            background-color: #b8dbd9;
            position: sticky;
            top: 0;
            align-self: flex-start;
            height: 100vh;
            height: 100dvh;
        }
        .image-cover {
            object-fit: cover;
            width: 100%;
            height: 100%;
            display: block;
        }
        .text-size-default {
            font-size: 1.20rem;
        }
        .lang-selector {
            position: absolute;
            top: 1.5rem;
            right: 1.8rem;
            z-index: 10;
        }

        .lang-select {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border: 1px solid rgba(44, 122, 123, 0.2);
            border-radius: 30px;
            padding: 0.4rem 1rem 0.4rem 1.2rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: #1e4f5f;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.02);
            transition: all 0.2s;
            cursor: pointer;
        }

        .lang-select:hover,
        .lang-select:focus {
            background: white;
            border-color: #2c7a7b;
            box-shadow: 0 6px 12px rgba(44, 122, 123, 0.08);
        }
        .icon-group {
            font-size: 1.2rem;
            padding: 5px;
        }
        .lang-select option {
            background: white;
            color: #1a3b44;
        }
        .input-group-text {
            background-color: #ffffff;
            border-right: none;
            border-radius: 14px 0 0 14px;
            color: #2c7a7b;
        }
        .form-control {
            border-left: none;
            border-radius: 0 14px 14px 0;
            padding: 0.75rem 1rem;
            background-color: #ffffff;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            border-color: #6fb4b3;
            box-shadow: 0 0 0 4px rgba(106, 175, 170, 0.18);
            background-color: #fff;
        }
        .input-group:focus-within .input-group-text {
            border-color: #6fb4b3;
            color: #1e595a;
        }
        .btn-login {
            background: #2c7a7b;
            border: none;
            border-radius: 3px;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            color: white;
            box-shadow: 0 8px 18px -6px rgba(44, 122, 123, 0.3);
            transition: all 0.25s;
        }
        .btn-login:hover, .btn-login:focus {
            background: #1f5e5f;
            box-shadow: 0 12px 22px -8px rgba(26, 90, 90, 0.4);
            transform: translateY(-1px);
            color: white;
        }
        .btn-login:active {
            transform: translateY(2px);
            box-shadow: 0 6px 12px -4px #1e5a5b;
        }
        .form-check {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            padding-left: 0;
        }
        .form-check .form-check-input {
            float: none;
            flex-shrink: 0;
            margin: 0.2em 0 0;
        }
        .form-check .form-check-label {
            white-space: normal;
        }
        .form-check-input:checked {
            background-color: #2c7a7b;
            border-color: #2c7a7b;
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(44, 122, 123, 0.2);
            border-color: #489b9c;
        }
        a {
            color: #2c7a7b;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.15s;
        }
        a:hover {
            color: #175f5f;
            text-decoration: underline;
        }
        /* botões sociais */
        .btn-social {
            border-radius: 3px;
            padding: 0.6rem 0.75rem;
            font-weight: 500;
            border: 1.5px solid #999999;
            background: white;
            color: #1e4f5f;
            transition: all 0.2s;
        }
        .btn-social:hover {
            background: #f0f9f8;
            border-color: #1e4f5f;
            box-shadow: 0 4px 8px rgba(0,0,0,0.02);
            color: #1a3b44;
        }
        .btn-social i {
            font-size: 1.1rem;
        }
        .alert, .brand, .btn-simple, .h1, .h2, .h3, .h4, .h5, .h6, .navbar, .td-name, a, body, button.close, h1, h2, h3, h4, h5, h6, p, td {
            font-family: var(--bs-body-font-family);
        }
        @media (max-width: 767.98px) {
            .form-col {
                background: rgba(255, 255, 255, 0.85);
                backdrop-filter: blur(15px);
                padding: 1.5rem;
            }
            .lang-selector {
                top: 1rem;
                right: 1.2rem;
            }
        }
        *:focus-visible {
            outline: 2px solid #2c7a7b;
            outline-offset: 2px;
        }
        .logo-text {
            color: #1e4f5f;
            letter-spacing: -0.5px;
        }
    </style>
    @yield('header')
</head>
<body>
@yield('content')

<script src="{{ mix('js/guest.js') }}"></script>
@yield('footer')
</body>
</html>
