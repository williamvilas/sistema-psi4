<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AgendaStatusEnum;
use App\Helpers\UserHelper;
use App\Http\Requests\Agenda\AttendanceRequest;
use App\Http\Requests\Agenda\AvailableRequest;
use App\Http\Requests\Agenda\ByPatientRequest;
use App\Http\Requests\Agenda\CancelRequest;
use App\Http\Requests\Agenda\PaginateRequest;
use App\Http\Requests\Agenda\StoreRequest;
use App\Services\AgendaService;
use App\Services\AgendaSettingService;
use App\Services\BookingSettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AgendaController extends Controller
{
    public function index(AgendaService $agendaService, BookingSettingService $bookingSettingService): View
    {
        return view('agenda.index', [
            'showGoogleCalendarHero' => $agendaService->shouldShowGoogleCalendarHero(Auth::user()),
            'publicBooking' => $bookingSettingService->publicLink(Auth::user()),
        ]);
    }

    public function dismissGoogleCalendarHero(AgendaService $agendaService): JsonResponse
    {
        return $this->response(function () use ($agendaService) {
            $agendaService->dismissGoogleCalendarHero(Auth::user());

            return $this->getMessageSuccess(message: __('agenda.google_hero_dismissed'));
        });
    }

    public function available(AvailableRequest $request, AgendaService $agendaService): JsonResponse
    {
        return $this->response(function () use ($request, $agendaService) {

            $times = $agendaService->availableSlotTypes(
                userId: Auth::id(),
                timezone: UserHelper::getTimezone(),
                date: $request->getDate(),
            );

            return [
                'html' => view('agenda.available', ['times' => $times])->render(),
                'data' => [
                    'date' => $request->getDate(),
                    'types' => $agendaService->typesOf($times),
                ],
            ];
        });
    }

    public function byPatient(ByPatientRequest $request, AgendaService $agendaService): JsonResponse
    {
        return $this->response(function () use ($request, $agendaService) {
            $patientId = $request->getPatientId();
            $timezone = UserHelper::getTimezone();
            $start = $request->getStart($timezone);
            $end = $request->getEnd($timezone);

            $agenda = $agendaService->byPatient(
                patientId: $patientId,
                userId: Auth::id(),
                perPage: (int) config('app.paginate'),
                start: $start,
                end: $end,
            );

            return [
                'items' => $agenda,
            ];
        });
    }

    public function store(StoreRequest $request, AgendaService $agendaService): JsonResponse
    {
        return $this->response(function () use ($request, $agendaService) {
            $agendaService->storeByPsychologist(data: $request->toInsert(), userId: Auth::id(), timezone: UserHelper::getTimezone());

            return [
                'link' => route('agenda.index', ['m' => 'scheduled']),
            ];
        });
    }

    public function paginate(PaginateRequest $request, AgendaService $agendaService, AgendaSettingService $agendaSettingService): JsonResponse
    {
        return $this->response(function () use ($request, $agendaService, $agendaSettingService) {
            $timezone = UserHelper::getTimezone();

            $paginate = $agendaService->paginate(
                params: $request->validated(),
                userId: Auth::id(),
                limit: config('app.paginate'),
                patientId: $request->getPatientId(),
                start: $request->getStart($timezone),
                end: $request->getEnd($timezone),
                pendingAttendance: $request->isPendingAttendance(),
            );

            return [
                'html' => view('agenda.paginate', [
                    'agenda' => $paginate,
                    'sendLinkText' => $agendaSettingService->sendLinkText(Auth::id()),
                ])->render(),
            ];
        });
    }

    public function cancel(CancelRequest $request, AgendaService $agendaService): JsonResponse
    {
        return $this->response(function () use ($request, $agendaService) {
            $agendaService->cancel(id: $request->getId(), userId: Auth::id());

            return $this->getMessageSuccess(message: __('agenda.canceled'));
        });
    }

    public function attendance(AttendanceRequest $request, AgendaService $agendaService): JsonResponse
    {
        return $this->response(function () use ($request, $agendaService) {
            $status = $request->getStatus();

            $agendaService->confirmAttendance(id: $request->getId(), userId: Auth::id(), status: $status);

            return $this->getMessageSuccess(
                message: __($status === AgendaStatusEnum::COMPLETED ? 'agenda.attendance_completed' : 'agenda.attendance_no_show')
            );
        });
    }
}
