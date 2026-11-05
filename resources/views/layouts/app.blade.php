<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('imgs/logos/logo-psi4.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@700&display=swap" rel="stylesheet">
    <link href="{{ mix('css/app.css') }}" rel="stylesheet">
    @php($isNotTwoFactor = ! \Illuminate\Support\Facades\Auth::user()?->hasTwoFactorEnabled())
    <style>
        @media (min-width: 576px) {
    .icon-refresh{margin-right: 0rem !important;}
}

    </style>
    @yield('header')
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar" data-background-color="dark">
        <div class="sidebar-logo">
            <!-- Logo Header -->
            <div class="logo-header" data-background-color="dark">
                <a href="{{ route('dashboard') }}" class="logo" aria-label="{{ config('app.name') }}">
                    <x-logo-psi4 :size="38" />
                </a>
                <div class="nav-toggle">
                    <button class="btn btn-toggle toggle-sidebar">
                        <i class="gg-menu-right"></i>
                    </button>
                    <button class="btn btn-toggle sidenav-toggler">
                        <i class="gg-menu-left"></i>
                    </button>
                </div>
            </div>
            <!-- End Logo Header -->
        </div>
        <div class="sidebar-wrapper scrollbar scrollbar-inner">
            <div class="sidebar-content">
                <ul class="nav nav-secondary">
                    <li class="nav-item {{ isLinkMenuActive('dashboard') }}">
                        <a href="{{ route('dashboard') }}">
                            @include('components.icon-dashboard')
                            <p>Dashboard</p>
                        </a>
                    </li>

                    <li class="nav-item {{ isLinkMenuActive('agenda.index') }}">
                        <a href="{{ route('agenda.index') }}">
                            @include('components.icon-agenda')
                            <p>Agenda</p>
                        </a>
                    </li>

                    {{--<li class="nav-item {{ isLinkMenuActive('patients.index') }}">
                        <a href="{{ route('patients.index') }}">
                            <i class="fas fa-graduation-cap"></i>
                            <p>Cursos</p>
                        </a>
                    </li>--}}

                    <li class="nav-item {{ isLinkMenuActive('patients.index') }}">
                        <a href="{{ route('patients.index') }}">
                            @include('components.icon-patient')
                            <p>Pacientes</p>
                        </a>
                    </li>

                    <li class="nav-item {{ isLinkMenuActive('medical-records.index') }}">
                        <a href="{{ route('medical-records.index') }}">
                            @include('components.icon-medical-records')
                            <p>Prontuários</p>
                        </a>
                    </li>

                    <li class="nav-item {{ isLinkMenuActive('documents.index') }}">
                        <a href="{{ route('documents.index') }}">
                            @include('components.icon-documents')
                            <p>Documentos</p>
                        </a>
                    </li>

                    {{--<li class="nav-item {{ isLinkMenuActive('agenda.index') }}">
                        <a href="{{ route('agenda.index') }}">
                            @include('components.icon-video')
                            <p>Teleconsulta</p>
                        </a>
                    </li>

                    <li class="nav-item {{ isLinkMenuActive('document-issuer.index') }}">
                        <a href="{{ route('document-issuer.index') }}">
                            @include('components.icon-document-issuer')
                            <p>Emitidos</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="#">
                            @include('components.icon-leads')
                            <p>Leads</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="#">
                            <i class="far fa-check-square"></i>
                            <p>Questionários</p>
                        </a>
                    </li>--}}

                    <li class="nav-item {{ isLinkMenuActive('payments.index') }}">
                        <a href="{{ route('payments.index') }}">
                            <i class="fas fa-money-bill-wave"></i>
                            <p>Pagamentos</p>
                        </a>
                    </li>

                    <li class="nav-item {{ isLinkMenuActive('health-receipts.index') }}">
                        <a href="{{ route('health-receipts.index') }}">
                            <i class="fas fa-file-invoice"></i>
                            <p>Receita Saúde</p>
                        </a>
                    </li>


                    @php($reportsActive = request()->routeIs('reports.*'))
                    <li class="nav-item {{ $reportsActive ? 'active submenu' : '' }}">
                        <a data-bs-toggle="collapse" href="#menu-reports" aria-expanded="{{ $reportsActive ? 'true' : 'false' }}">
                            <i class="fas fa-chart-line"></i>
                            <p>Relatórios</p>
                            <span class="caret"></span>
                        </a>
                        <div class="collapse {{ $reportsActive ? 'show' : '' }}" id="menu-reports">
                            <ul class="nav nav-collapse">
                                <li class="{{ isLinkMenuActive('reports.financial') }}">
                                    <a href="{{ route('reports.financial') }}">
                                        <span class="sub-item">Financeiro</span>
                                    </a>
                                </li>
                                <li class="{{ isLinkMenuActive('reports.attendance') }}">
                                    <a href="{{ route('reports.attendance') }}">
                                        <span class="sub-item">Faltas e cancelamentos</span>
                                    </a>
                                </li>
                                <li class="{{ isLinkMenuActive('reports.occupancy') }}">
                                    <a href="{{ route('reports.occupancy') }}">
                                        <span class="sub-item">Ocupação da agenda</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    <li class="nav-item {{ isLinkMenuActive('settings.index') }}">
                        <a href="{{ route('settings.index') }}">
                            @include('components.icon-settings')
                            <p>Configurações</p>
                        </a>
                    </li>

                    <li class="nav-item {{ isLinkMenuActive('help.index') }}">
                        <a href="{{ route('help.index') }}">
                            @include('components.icon-help')
                            <p>Ajuda</p>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="sidebar-user dropup">
            @php($user = \Illuminate\Support\Facades\Auth::user())
            @php($currentPlan = \App\Helpers\UserHelper::getCurrentPlan($user))
            <a class="sidebar-user-toggle" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                <div class="avatar-sm">
                    <img
                        src="{{ \App\Helpers\UserHelper::getPhoto($user) }}"
                        alt="Foto de perfil"
                        class="avatar-img img-user-updated rounded-circle {{ getColorPlanByUser($currentPlan) }}"
                    />
                </div>
                <span class="sidebar-user-info">
                    <span class="fw-bold user-name d-block">{{ \App\Helpers\UserHelper::getFirstName($user) }}</span>
                    <small class="sidebar-user-plan">
                        {!! getIconPlanByUser($currentPlan) !!}
                        {{ 'Plano '.__('plans.'.$currentPlan) }}
                    </small>
                </span>
            </a>
            <ul class="dropdown-menu sidebar-user-menu">
                <li>
                    <a class="dropdown-item menu-profile" href="{{ route('profile.edit') }}">
                        <i class="fas fa-user me-2"></i>
                        Meu Perfil
                    </a>
                </li>
                <li>
                    <a class="dropdown-item menu-profile" href="{{ route('plans.index') }}">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Planos
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                    <a class="dropdown-item menu-profile" href="#" onclick="logoutClearingMedicalKey(event);">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        Sair
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <!-- End Sidebar -->

    <div class="main-panel">
        <div class="main-header main-header-mobile">
            <div class="main-header-logo">
                <!-- Logo Header -->
                <div class="logo-header" data-background-color="dark">
                    <a href="{{ route('dashboard') }}" class="logo" aria-label="{{ config('app.name') }}">
                        <x-logo-psi4 :size="30" />
                    </a>
                    <div class="nav-toggle">
                        <button class="btn btn-toggle toggle-sidebar">
                            <i class="gg-menu-right"></i>
                        </button>
                        <button class="btn btn-toggle sidenav-toggler">
                            <i class="gg-menu-left"></i>
                        </button>
                    </div>
                </div>
                <!-- End Logo Header -->
            </div>
        </div>

        <div class="container container-page-main">
            @if($isNotTwoFactor)
                <div class="two-factor-banner" role="status">
                    <i class="fa-solid fa-shield-halved two-factor-banner-icon" aria-hidden="true"></i>
                    <span class="two-factor-banner-text">
                        <strong>Proteja sua conta:</strong>
                        <span class="two-factor-banner-detail">ative a autenticação de 2 fatores.</span>
                    </span>
                    <a href="{{ route('two-factor.index') }}" class="two-factor-banner-btn">Ativar agora</a>
                </div>
            @endif
            <div class="page-inner">
                @include('components.plan-usage-banner')
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
@include('components.message-updated')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            document.querySelectorAll('input').forEach(input => {
                input.setAttribute('autocomplete', 'off');
            });
        }, 300);
    });
    document.addEventListener('DOMContentLoaded', function() {
        const reportsToggle = document.querySelector('a[href="#menu-reports"]');
        if (!reportsToggle) return;

        reportsToggle.addEventListener('click', function() {
            document.querySelectorAll('.sidebar .nav-item.active').forEach(item => item.classList.remove('active'));
            reportsToggle.closest('.nav-item').classList.add('active');
        });
    });
    window.API_INTEGRATION_MICROSERVICE = @json(config('services.integrations.url'));
    window.USER_UUID = "{{ auth()->user()?->uuid }}";
    window.USER_EMAIL = @json(auth()->user()?->email);
</script>
@yield('footer')
</body>
</html>
