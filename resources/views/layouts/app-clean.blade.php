<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'Dashboard')</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('imgs/logos/psi4.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@700&display=swap" rel="stylesheet">
    <link href="{{ mix('css/app.css') }}" rel="stylesheet">
    @yield('header')
</head>
<body>
<div class="wrapper">

    <div class="main-panel main-panel-force">
        <div class="main-header main-header-force">
            <div class="main-header-logo">
                <!-- Logo Header -->
                <div class="logo-header" data-background-color="dark">
                    <a href="{{ route('dashboard') }}" class="logo" aria-label="{{ config('app.name') }}">
                        <x-logo-psi4 :size="30" />
                    </a>
                </div>
                <!-- End Logo Header -->
            </div>
            <!-- Navbar Header -->
            <nav
                class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom"
            >
                <div class="container-fluid">

                    <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
                        <li
                            class="nav-item topbar-icon dropdown hidden-caret d-flex d-lg-none"
                        >
                            <a
                                class="nav-link dropdown-toggle"
                                data-bs-toggle="dropdown"
                                href="#"
                                role="button"
                                aria-expanded="false"
                                aria-haspopup="true"
                            >
                                <i class="fa fa-search"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-search animated fadeIn">
                                <form class="navbar-left navbar-form nav-search">
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            placeholder="Search ..."
                                            class="form-control"
                                        />
                                    </div>
                                </form>
                            </ul>
                        </li>

                    </ul>
                </div>
            </nav>
            <!-- End Navbar -->
        </div>

        <div class="container">
            <div class="page-inner">
                @yield('content')
            </div>
        </div>

        <footer class="footer">
            <div class="container-fluid d-flex justify-content-between">
                <div class="copyright">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.
                </div>
            </div>
        </footer>
    </div>

</div>
<script src="{{ mix('js/app.js') }}" defer></script>
@yield('footer')
</body>
</html>
