<?php

declare(strict_types=1);

use App\Http\Controllers\AccessLogsController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AgendaSettingsController;
use App\Http\Controllers\AiUsageController;
use App\Http\Controllers\BookingSettingsController;
use App\Http\Controllers\CnpjController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentIssuerController;
use App\Http\Controllers\DocumentsController;
use App\Http\Controllers\EncryptionKeyController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\FunnelEventController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\HealthReceiptBatchesController;
use App\Http\Controllers\HealthReceiptsController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\MedicalRecordsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PatientsController;
use App\Http\Controllers\PaymentAccountController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PlansController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PsychologistSettingsController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicScaleController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ScaleApplicationsController;
use App\Http\Controllers\ScaleLinksController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\SessionActivitiesController;
use App\Http\Controllers\SessionTranscriptionsController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\VideoRoomAuthorizationsController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\ZipcodeController;
use App\Http\Middleware\CustomAuthenticate;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Http\Middleware\ForceChangePassword;
use App\Http\Middleware\RedirectIfOnboardingCompleted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::prefix('/')->group(function () {
    Route::get('', function () {
        return view('landing.index');
    })->name('page-main');
});

Route::get('/whatsapp', [WhatsAppController::class, 'contact'])->middleware('throttle:60,1')->name('whatsapp.contact');

Route::view('/politica-de-privacidade', 'privacy-policy')->name('privacy-policy');
Route::view('/termos-de-uso', 'terms-service')->name('terms-service');
Route::view('/seguranca', 'security')->name('security');

Route::prefix('/lp/{slug}')->where(['slug' => '[a-z0-9]+(-[a-z0-9]+)*'])->group(function () {
    Route::get('', [PublicBookingController::class, 'show'])->name('public-booking.show');
    Route::get('/availability', [PublicBookingController::class, 'availability'])->middleware('throttle:30,1')->name('public-booking.availability');
    Route::post('/book', [PublicBookingController::class, 'book'])->middleware('throttle:5,1')->name('public-booking.book');
    Route::get('/confirmado', [PublicBookingController::class, 'confirmed'])->name('public-booking.confirmed');
});

// Escala respondida pelo paciente por link (sem login). O token tem 43 caracteres base64url.
Route::prefix('/avaliacao/{token}')->where(['token' => '[A-Za-z0-9_-]{43}'])->group(function () {
    Route::get('', [PublicScaleController::class, 'show'])->middleware('throttle:30,1')->name('public-scale.show');
    Route::post('/upload-url', [PublicScaleController::class, 'uploadUrl'])->middleware('throttle:5,1')->name('public-scale.upload-url');
    Route::post('', [PublicScaleController::class, 'submit'])->middleware('throttle:5,1')->name('public-scale.submit');
});

Route::get('/pagamento/confirmacao', function () {
    return view('payments.confirmation', ['status' => request('status', 'pending')]);
})->name('payments.confirmation');

Route::prefix('/logs')->group(function () {
    Route::post('', [LogsController::class, 'store'])->name('logs.store');
});

Route::post('/track', [FunnelEventController::class, 'store'])->middleware('throttle:60,1')->name('funnel.track');
Route::post('/screen-resolution', [ScreenController::class, 'store'])->name('screen.store');

Route::middleware([CustomAuthenticate::class, ForceChangePassword::class, RedirectIfOnboardingCompleted::class])->prefix('onboarding')->group(function () {
    Route::get('/', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/agenda', [OnboardingController::class, 'storeAgenda'])->name('onboarding.agenda.store');
    Route::post('/psychologist', [OnboardingController::class, 'storePsychologist'])->name('onboarding.psychologist.store');
    Route::post('/booking', [OnboardingController::class, 'storeBooking'])->name('onboarding.booking.store');
    Route::get('/slug-availability', [OnboardingController::class, 'checkSlug'])->middleware('throttle:30,1')->name('onboarding.slug-availability');
    Route::get('/national-holidays', [OnboardingController::class, 'nationalHolidays'])->name('onboarding.national-holidays');
    Route::get('/zipcode/{zipcode}', [ZipcodeController::class, 'show'])->middleware('throttle:30,1')->name('onboarding.zipcode.show');
});

Route::middleware([CustomAuthenticate::class, ForceChangePassword::class, EnsureOnboardingCompleted::class])->group(callback: function () {
    Route::prefix('two-factor')->group(function () {
        Route::get('/', [TwoFactorController::class, 'index'])->name('two-factor.index');
        Route::post('/setup', [TwoFactorController::class, 'setup'])->middleware('throttle:10,1')->name('two-factor.setup');
        Route::post('/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('two-factor.confirm');
        Route::post('/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->middleware('throttle:10,1')->name('two-factor.recovery-codes');
        Route::delete('/', [TwoFactorController::class, 'destroy'])->middleware('throttle:10,1')->name('two-factor.destroy');
    });

    Route::prefix('encryption-key')->group(function () {
        Route::get('/', [EncryptionKeyController::class, 'index'])->name('encryption-key.index');
        Route::get('/details', [EncryptionKeyController::class, 'show'])->middleware('throttle:30,1')->name('encryption-key.show');
        Route::post('/', [EncryptionKeyController::class, 'store'])->middleware('throttle:5,1')->name('encryption-key.store');
        Route::put('/passphrase', [EncryptionKeyController::class, 'updatePassphrase'])->middleware('throttle:10,1')->name('encryption-key.passphrase.update');
        Route::put('/recovery', [EncryptionKeyController::class, 'updateRecovery'])->middleware('throttle:10,1')->name('encryption-key.recovery.update');
        Route::post('/scale-key-pair', [EncryptionKeyController::class, 'storeScaleKeyPair'])->middleware('throttle:5,1,scale-key-pair')->name('encryption-key.scale-key-pair.store');
    });

    Route::prefix('access-logs')->group(function () {
        Route::get('/', [AccessLogsController::class, 'index'])->name('access-logs.index');
        Route::get('/paginate', [AccessLogsController::class, 'paginate'])->name('access-logs.paginate');
    });

    Route::get('/ai-usage', [AiUsageController::class, 'index'])->middleware('throttle:60,1')->name('ai-usage.index');

    Route::prefix('files')->group(function () {
        Route::post('/upload-url', [FilesController::class, 'temporaryUrlToUpload'])->name('files.temporaryUrl');
    });

    Route::prefix('agenda')->group(function () {
        Route::get('/', [AgendaController::class, 'index'])->name('agenda.index');

        Route::get('/available', [AgendaController::class, 'available'])->name('agenda.available');
        Route::post('/', [AgendaController::class, 'store'])->name('agenda.store');
        Route::get('/paginate', [AgendaController::class, 'paginate'])->name('agenda.paginate');
        Route::patch('/cancel', [AgendaController::class, 'cancel'])->name('agenda.cancel');
        Route::patch('/attendance', [AgendaController::class, 'attendance'])->name('agenda.attendance');
        Route::patch('/google-calendar-hero/dismiss', [AgendaController::class, 'dismissGoogleCalendarHero'])->name('agenda.google-calendar-hero.dismiss');
    });

    Route::prefix('patients')->group(function () {
        Route::get('/', function () {
            return view('patients.index');
        })->name('patients.index');

        Route::get('/create', function () {
            return view('patients.create');
        })->name('patients.create');

        Route::get('/edit/{id}', [PatientsController::class, 'edit'])->name('patients.edit');

        Route::post('/', [PatientsController::class, 'store'])->name('patients.store');
        Route::put('/{id}', [PatientsController::class, 'update'])->name('patients.update');
        Route::delete('/', [PatientsController::class, 'delete'])->name('patients.delete');
        Route::get('/autocomplete', [PatientsController::class, 'autocomplete'])->name('patients.autocomplete');
        Route::get('/exists', [PatientsController::class, 'exists'])->name('patients.exists');
        Route::get('/paginate', [PatientsController::class, 'paginate'])->name('patients.paginate');
        Route::get('/{id}', [PatientsController::class, 'show'])->name('patients.show');
        Route::get('/{id}/evolution', [PatientsController::class, 'evolution'])->name('patients.evolution');
    });

    Route::prefix('medical-records')->group(function () {
        Route::get('/', function () {
            return view('medical-records.index', [
                'pendingTranscriptions' => app(\App\Services\SessionTranscriptionService::class)->countPending(\Illuminate\Support\Facades\Auth::id()),
            ]);
        })->name('medical-records.index');

        Route::get('/create', [MedicalRecordsController::class, 'create'])->name('medical-records.create');
        Route::delete('/', [MedicalRecordsController::class, 'delete'])->middleware('throttle:10,1')->name('medical-records.delete');
        Route::post('/export', [MedicalRecordsController::class, 'export'])->middleware('throttle:5,1')->name('medical-records.export');
        Route::get('/paginate', [MedicalRecordsController::class, 'paginate'])->name('medical-records.paginate');
        Route::post('/', [MedicalRecordsController::class, 'store'])->name('medical-records.store');
        Route::get('/edit/{id}', [MedicalRecordsController::class, 'edit'])->name('medical-records.edit');
        Route::get('/{id}', [MedicalRecordsController::class, 'show'])->name('medical-records.show');
        Route::put('/{id}', [MedicalRecordsController::class, 'update'])->name('medical-records.update');
    });

    Route::prefix('scale-applications')->group(function () {
        Route::post('/', [ScaleApplicationsController::class, 'store'])->name('scale-applications.store');
        Route::post('/list', [ScaleApplicationsController::class, 'list'])->middleware('throttle:20,1')->name('scale-applications.list');
        Route::delete('/', [ScaleApplicationsController::class, 'delete'])->middleware('throttle:10,1')->name('scale-applications.delete');
    });

    Route::prefix('session-transcriptions')->whereUuid('uuid')->group(function () {
        Route::get('/', [SessionTranscriptionsController::class, 'index'])->name('session-transcriptions.index');
        Route::get('/paginate', [SessionTranscriptionsController::class, 'paginate'])->name('session-transcriptions.paginate');
        Route::get('/history', [SessionTranscriptionsController::class, 'history'])->name('session-transcriptions.history');
        Route::get('/history/paginate', [SessionTranscriptionsController::class, 'paginateHistory'])->name('session-transcriptions.history.paginate');
        Route::get('/{uuid}', [SessionTranscriptionsController::class, 'show'])->name('session-transcriptions.show');
        Route::get('/{uuid}/note', [SessionTranscriptionsController::class, 'note'])->middleware('throttle:10,1,transcription-note')->name('session-transcriptions.note');
        Route::get('/{uuid}/consent', [SessionTranscriptionsController::class, 'consent'])->name('session-transcriptions.consent');
        Route::post('/{uuid}/accept', [SessionTranscriptionsController::class, 'accept'])->middleware('throttle:10,1,transcription-decision')->name('session-transcriptions.accept');
        Route::delete('/{uuid}', [SessionTranscriptionsController::class, 'destroy'])->middleware('throttle:10,1,transcription-decision')->name('session-transcriptions.destroy');
    });

    Route::prefix('video-room-authorizations')->where(['id' => '[A-Za-z0-9]{64}'])->group(function () {
        Route::get('/{id}', [VideoRoomAuthorizationsController::class, 'show'])->name('video-room-authorizations.show');
        Route::post('/{id}', [VideoRoomAuthorizationsController::class, 'approve'])->middleware('throttle:10,1,authorization-approve')->name('video-room-authorizations.approve');
    });

    Route::prefix('session-activities')->group(function () {
        Route::get('/', [SessionActivitiesController::class, 'index'])->name('session-activities.index');
        Route::put('/pin', [SessionActivitiesController::class, 'updatePin'])->middleware('throttle:10,1')->name('session-activities.pin.update');
        Route::post('/thermometer', [SessionActivitiesController::class, 'thermometer'])->middleware('throttle:20,1')->name('session-activities.thermometer');
    });

    Route::prefix('scale-links')->group(function () {
        Route::get('/', [ScaleLinksController::class, 'paginate'])->name('scale-links.paginate');
        Route::post('/', [ScaleLinksController::class, 'store'])->middleware('throttle:30,1')->name('scale-links.store');
        Route::patch('/revoke', [ScaleLinksController::class, 'revoke'])->middleware('throttle:30,1')->name('scale-links.revoke');
    });

    Route::prefix('zipcode')->group(function () {
        Route::get('/{zipcode}', [ZipcodeController::class, 'show'])->middleware('throttle:30,1')->name('zipcode.show');
    });

    Route::prefix('cnpj')->group(function () {
        Route::get('/{cnpj}', [CnpjController::class, 'show'])->middleware('throttle:30,1')->name('cnpj.show');
    });

    Route::prefix('whatsapp')->group(function () {
        Route::get('/send/{phone}', [WhatsAppController::class, 'send'])->name('whatsapp.send');
        Route::get('/help', [WhatsAppController::class, 'help'])->name('whatsapp.help');
    });

    Route::prefix('documents')->group(function () {
        Route::get('/', function () {
            return view('documents.index');
        })->name('documents.index');

        /*Route::get('/create', function () {
            return view('documents.create');
        })->name('documents.create');*/
        Route::get('/download/{id}', [DocumentsController::class, 'download'])->name('documents.download');
        Route::post('/download/{id}', [DocumentsController::class, 'download'])->name('documents.download.post');
        Route::get('/fields/{id}', [DocumentsController::class, 'fields'])->name('documents.fields');
        Route::get('/first-session/{patient_id}', [DocumentsController::class, 'firstSession'])->name('documents.firstSession');
        Route::post('/defaults/{id}', [DocumentsController::class, 'saveDefault'])->name('documents.defaults.save');
        Route::delete('/defaults/{id}', [DocumentsController::class, 'resetDefault'])->name('documents.defaults.reset');
        Route::get('/agenda-by-patient/{patient_id}', [DocumentsController::class, 'agendaByPatient'])->name('documents.agendaByPatient');
        Route::post('/pdf/{id}', [DocumentsController::class, 'pdf'])->name('documents.pdf');
        Route::get('/edit/{id}', [DocumentsController::class, 'edit'])->name('documents.edit');
        Route::get('/preview/{id}', [DocumentsController::class, 'preview'])->name('documents.preview');
        Route::post('/preview/{id}', [DocumentsController::class, 'preview'])->name('documents.preview.post');
        Route::get('/paginate', [DocumentsController::class, 'paginate'])->name('documents.paginate');
        Route::delete('/', [DocumentsController::class, 'delete'])->name('documents.delete');
    });

    Route::prefix('agenda')->group(function () {
        Route::get('/by-patient', [AgendaController::class, 'byPatient'])->name('agenda.by-patient');
    });

    Route::prefix('document-issuer')->group(function () {
        Route::get('/', function () {
            return view('document-issuer.index');
        })->name('document-issuer.index');

        Route::get('/paginate', [DocumentIssuerController::class, 'paginate'])->name('document-issuer.paginate');
    });

    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('settings.index');

        Route::post('/agenda', [AgendaSettingsController::class, 'store'])->name('settings.agenda.store');
        Route::post('/psychologist', [PsychologistSettingsController::class, 'store'])->name('settings.psychologist.store');
        Route::post('/booking', [BookingSettingsController::class, 'store'])->name('settings.booking.store');
        Route::get('/booking/slug-availability', [BookingSettingsController::class, 'checkSlug'])->middleware('throttle:30,1')->name('settings.booking.slug-availability');
        Route::post('/booking/toggle', [BookingSettingsController::class, 'toggle'])->name('settings.booking.toggle');
        Route::get('/paginate', [SettingController::class, 'paginate'])->name('settings.paginate');
        Route::get('/national-holidays', [SettingController::class, 'nationalHolidays'])->name('settings.national-holidays');
        Route::get('/payment-account/connect', [PaymentAccountController::class, 'connect'])->name('settings.payment-account.connect');
        Route::get('/payment-account/callback', [PaymentAccountController::class, 'callback'])->name('settings.payment-account.callback');
        Route::delete('/payment-account', [PaymentAccountController::class, 'disconnect'])->name('settings.payment-account.disconnect');

        Route::get('/google-calendar/connect', [GoogleCalendarController::class, 'connect'])->name('settings.google-calendar.connect');
        Route::get('/google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('settings.google-calendar.callback');
        Route::get('/google-calendar/calendars', [GoogleCalendarController::class, 'calendars'])->name('settings.google-calendar.calendars');
        Route::put('/google-calendar', [GoogleCalendarController::class, 'update'])->name('settings.google-calendar.update');
        Route::delete('/google-calendar', [GoogleCalendarController::class, 'disconnect'])->name('settings.google-calendar.disconnect');
    });

    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentsController::class, 'index'])->name('payments.index');

        Route::get('/create', [PaymentsController::class, 'create'])->name('payments.create');

        Route::get('/paginate', [PaymentsController::class, 'paginate'])->name('payments.paginate');
        Route::post('/', [PaymentsController::class, 'store'])->name('payments.store');
        Route::delete('/', [PaymentsController::class, 'delete'])->name('payments.delete');
        Route::get('/edit/{id}', [PaymentsController::class, 'edit'])->name('payments.edit');
        Route::put('/{id}', [PaymentsController::class, 'update'])->name('payments.update');
        Route::post('/{id}/refund', [PaymentsController::class, 'refund'])->name('payments.refund');
        Route::get('/{id}', [PaymentsController::class, 'show'])->name('payments.show');
    });

    Route::prefix('health-receipts')->group(function () {
        Route::get('/', [HealthReceiptsController::class, 'index'])->name('health-receipts.index');
        Route::get('/paginate', [HealthReceiptsController::class, 'paginate'])->name('health-receipts.paginate');
        Route::post('/', [HealthReceiptsController::class, 'store'])->name('health-receipts.store');

        Route::get('/batches/paginate', [HealthReceiptBatchesController::class, 'paginate'])->name('health-receipts.batches.paginate');
        Route::post('/batches', [HealthReceiptBatchesController::class, 'store'])->name('health-receipts.batches.store');
        Route::get('/batches/{id}/download', [HealthReceiptBatchesController::class, 'download'])->name('health-receipts.batches.download');
        Route::post('/batches/{id}/imported', [HealthReceiptBatchesController::class, 'imported'])->name('health-receipts.batches.imported');
        Route::post('/batches/{id}/undo', [HealthReceiptBatchesController::class, 'undo'])->name('health-receipts.batches.undo');

        Route::put('/{id}', [HealthReceiptsController::class, 'update'])->name('health-receipts.update');
        Route::delete('/{id}', [HealthReceiptsController::class, 'delete'])->name('health-receipts.delete');
        Route::post('/{id}/skip', [HealthReceiptsController::class, 'skip'])->name('health-receipts.skip');
        Route::post('/{id}/restore', [HealthReceiptsController::class, 'restore'])->name('health-receipts.restore');
    });

    Route::prefix('reports')->group(function () {
        Route::get('/financial', [ReportsController::class, 'financial'])->name('reports.financial');
        Route::get('/financial/data', [ReportsController::class, 'financialData'])->name('reports.financial.data');

        Route::get('/attendance', [ReportsController::class, 'attendance'])->name('reports.attendance');
        Route::get('/attendance/data', [ReportsController::class, 'attendanceData'])->name('reports.attendance.data');
        Route::get('/attendance/export', [ReportsController::class, 'attendanceExport'])->name('reports.attendance.export');

        Route::get('/occupancy', [ReportsController::class, 'occupancy'])->name('reports.occupancy');
        Route::get('/occupancy/data', [ReportsController::class, 'occupancyData'])->name('reports.occupancy.data');
    });

    Route::prefix('help')->group(function () {
        Route::get('/', [HelpController::class, 'index'])->name('help.index');
    });

    Route::prefix('dashboard')->group(function () {
        Route::get('/', function () {
            return view('dashboard.index');
        })->name('dashboard');

        Route::get('/report', [DashboardController::class, 'report'])->name('dashboard.report');
    });

    Route::prefix('plans')->group(function () {
        Route::get('/', [PlansController::class, 'index'])->name('plans.index');

        Route::get('/{plan}/options', [PlansController::class, 'options'])->name('plans.options');

        Route::post('/checkout', [PlansController::class, 'checkout'])->name('plans.checkout');

        Route::post('/cancel-renewal', [PlansController::class, 'cancelRenewal'])->name('plans.cancel-renewal');
    });
});

Route::middleware(['auth'])->group(function () {

    Route::middleware([ForceChangePassword::class, EnsureOnboardingCompleted::class])->group(function () {

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

        Route::get('/change-password', function () {
            return view('auth.change-password');
        })->name('profile.change-password');

        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/photo', [ProfileController::class, 'updatePhoto'])->name('profile.updatePhoto');
        // Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    Route::get('/force-change-password', function () {
        $user = Auth::user();
        if (empty($user->force_change_password)) {
            return redirect()->route('dashboard');
        }

        return view('auth.force-change-password');
    })->name('profile.force-change-password');
});

require __DIR__.'/auth.php';
