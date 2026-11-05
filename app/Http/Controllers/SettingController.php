<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\HolidayHelper;
use App\Services\AgendaSettingService;
use App\Services\BookingSettingService;
use App\Services\EncryptionKeyService;
use App\Services\GoogleCalendarService;
use App\Services\PaymentAccountService;
use App\Services\PsychologistSettingService;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
{
    public function __construct(
        private readonly AgendaSettingService $agendaSettingService,
        private readonly PsychologistSettingService $psychologistSettingService,
        private readonly BookingSettingService $bookingSettingService,
        private readonly PaymentAccountService $paymentAccountService,
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly EncryptionKeyService $encryptionKeyService,
    ) {
    }

    public function index()
    {
        return view('settings.index', [
            'agendaSettings' => $this->agendaSettingService->details(userId: Auth::id()),
            'psychologistSettings' => $this->psychologistSettingService->details(userId: Auth::id()),
            'bookingSettings' => $this->bookingSettingService->details(userId: Auth::id()),
            'services' => $this->bookingSettingService->services(userId: Auth::id()),
            'paymentAccount' => $this->paymentAccountService->details(userId: Auth::id()),
            'googleCalendar' => $this->googleCalendarService->details(userId: Auth::id()),
            'encryptionKeyExists' => $this->encryptionKeyService->exists(Auth::id()),
        ]);
    }

    public function paginate()
    {
        return $this->response(function () {

            return [
                'html' => view('settings.paginate')->render(),
            ];
        });
    }

    public function nationalHolidays()
    {
        return $this->response(fn () => ['holidays' => HolidayHelper::national()]);
    }
}
