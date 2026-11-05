const mix = require('laravel-mix');

// --- INICIO LANDING PAGE ----
// Pacote enxuto: a landing não usa formulários nem diálogos (LCP < 2,5s).
mix.styles([
    'node_modules/bootstrap/dist/css/bootstrap.min.css',
    'node_modules/bootstrap-icons/font/bootstrap-icons.min.css',
    'resources/css/landing-page.css'
], 'public/css/lp.css').version();

mix.combine([
    'resources/js/jquery.min.js',
    'node_modules/bootstrap/dist/js/bootstrap.min.js',
    'resources/js/default.js',
    'resources/js/landing.js',
], 'public/js/lp.js').version();

mix.copy('node_modules/bootstrap-icons/font/fonts', 'public/css/fonts/');
// --- FIM LANDING PAGE ----

// --- INICIO DO DASHBOARD ----
mix.styles([
    'node_modules/@fortawesome/fontawesome-free/css/all.min.css',
    'node_modules/select2/dist/css/select2.min.css',
    'node_modules/sweetalert2/dist/sweetalert2.min.css',
    'node_modules/bootstrap/dist/css/bootstrap.min.css',
    'node_modules/highcharts/css/highcharts.css',
    'resources/css/plugin.css',
    'resources/css/kaiadmin.min.css',
    'node_modules/daterangepicker/daterangepicker.css',
    'resources/css/picker-custom.css',
    'resources/css/demo.css',
    'resources/css/custom.css',
    'resources/css/dialog.css'
], 'public/css/app.css').version();

mix.combine([
    'resources/js/jquery.min.js',
    'node_modules/select2/dist/js/select2.min.js',
    'node_modules/select2/dist/js/i18n/pt-BR.js',
    'resources/js/popper.min.js',
    'node_modules/bootstrap/dist/js/bootstrap.min.js',
    'resources/plugins/jquery-scrollbar/jquery.scrollbar.min.js',
    'resources/js/popperjs.js',
    'node_modules/moment/min/moment-with-locales.js',
    'node_modules/sweetalert2/dist/sweetalert2.all.min.js',
    'node_modules/highcharts/highmaps.js',
    'resources/js/jquery.mask.min.js',
    'resources/js/kaiadmin.min.js',
    'node_modules/daterangepicker/daterangepicker.js',
    'resources/js/jquery.maskMoney.js',
    'resources/js/picker.js',
    'node_modules/dompurify/dist/purify.min.js',
    'resources/js/default.js',
    'resources/js/crypto.js',
    'resources/js/ia-assistant.js',
    'resources/js/autocomplete-patient.js',
    'resources/js/screen.js',
    'resources/js/dashboard.js',
    'resources/js/users.js',
    'resources/js/patients.js',
    'resources/js/patient-evolution.js',
    'resources/js/scale-links.js',
    'resources/js/session-activities.js',
    'resources/js/session-transcriptions.js',
    'resources/js/video-room-authorizations.js',
    'resources/js/agenda.js',
    'resources/js/medical-records.js',
    'resources/js/documents.js',
    'resources/js/document-issuer.js',
    'resources/js/photo-cropper.js',
    'resources/js/schedule-settings.js',
    'resources/js/zipcode-lookup.js',
    'resources/js/settings.js',
    'resources/js/otp-input.js',
    'resources/js/two-factor.js',
    'resources/js/encryption-key.js',
    'resources/js/access-logs.js',
    'resources/js/payments.js',
    'resources/js/health-receipts.js',
    'resources/js/reports.js',
    'resources/js/plans.js',
], 'public/js/app.js').version();

mix.copy('node_modules/@fortawesome/fontawesome-free/webfonts', 'public/webfonts/');
mix.copy('node_modules/@fortawesome/fontawesome-free/svgs', 'public/svgs/');
// --- FIM DO DASHBOARD ----

// --- INICIO DO CONVIDADO ----
mix.combine([
    'resources/js/jquery.min.js',
    'resources/js/jquery.mask.min.js',
    'resources/js/jquery.maskMoney.js',
    'node_modules/bootstrap/dist/js/bootstrap.min.js',
    'node_modules/sweetalert2/dist/sweetalert2.all.min.js',
    'resources/js/default.js',
    'resources/js/otp-input.js',
    'resources/js/guest.js',
], 'public/js/guest.js').version();
// --- FIM DO CONVIDADO ----

// --- INICIO ONBOARDING ----
// jQuery e default.js já vêm do guest.js carregado pelo layout.
mix.combine([
    'resources/js/photo-cropper.js',
    'resources/js/schedule-settings.js',
    'resources/js/zipcode-lookup.js',
    'resources/js/onboarding.js',
], 'public/js/onboarding.js').version();
// --- FIM ONBOARDING ----

// --- INICIO AGENDAMENTO PUBLICO ----
// Os diálogos (appDialog) usam o mesmo visual do painel.
mix.styles([
    'resources/css/dialog.css',
], 'public/css/dialog.css').version();

mix.combine([
    'resources/js/jquery.min.js',
    'resources/js/jquery.mask.min.js',
    'node_modules/sweetalert2/dist/sweetalert2.all.min.js',
    'resources/js/default.js',
    'resources/js/public-booking.js',
], 'public/js/public-booking.js').version();
// --- FIM AGENDAMENTO PUBLICO ----

// --- INICIO ESCALA PUBLICA (paciente responde por link) ----
// Sem jQuery nem CDN: a página não carrega nada de terceiros.
mix.styles([
    'resources/css/public-scale.css',
], 'public/css/public-scale.css').version();

mix.combine([
    'resources/js/crypto.js',
    'resources/js/public-scale.js',
], 'public/js/public-scale.js').version();
// --- FIM ESCALA PUBLICA ----


mix.styles([
    'resources/css/editor-texto.css',
], 'public/css/edit-text.css').version();

mix.styles([
    'resources/css/medical-doc.css',
], 'public/css/medical-doc.css').version();

mix.styles([
    'resources/css/medical-doc.css',
    'resources/css/medical-editor.css',
], 'public/css/medical-editor.css').version();

// Módulo ES (TipTap): precisa passar pelo webpack/babel, ao contrário dos demais scripts (combine).
mix.js('resources/js/medical-editor.js', 'public/js/medical-editor.js').version();

