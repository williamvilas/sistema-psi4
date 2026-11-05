<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\GoogleCalendarScopeMissingException;
use App\Http\Requests\GoogleCalendar\CallbackRequest;
use App\Http\Requests\GoogleCalendar\ConnectRequest;
use App\Http\Requests\GoogleCalendar\UpdateRequest;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GoogleCalendarController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $googleCalendarService)
    {
    }

    /**
     * Redireciona o psicólogo para a tela de autorização do Google.
     */
    public function connect(ConnectRequest $request): RedirectResponse
    {
        return redirect()->away($this->googleCalendarService->buildAuthorizationUrl(Auth::id(), $request->getOrigin()));
    }

    /**
     * Callback OAuth: troca o `code` pelos tokens da conta Google e volta
     * para a tela de configurações com o resultado.
     */
    public function callback(CallbackRequest $request): RedirectResponse
    {
        $origin = $this->googleCalendarService->pullOrigin($request->getState());

        if ($request->getError()) {
            return $this->redirectToSettings('denied');
        }

        try {
            $this->googleCalendarService->connect(
                code: (string) $request->getCode(),
                state: $request->getState(),
                userId: Auth::id(),
            );
        } catch (GoogleCalendarScopeMissingException) {
            return $this->redirectToSettings('scope_missing');
        } catch (\DomainException $d) {
            return $this->redirectToSettings('error', $d->getMessage());
        } catch (\Throwable $t) {
            Log::error('Falha inesperada ao conectar o Google Calendar.', [
                'user_id' => Auth::id(),
                'exception' => $t->getMessage(),
                'class' => $t::class,
            ]);

            return $this->redirectToSettings('error', __('google-calendar.oauth_failed'));
        }

        // Volta para a agenda quando a conexão partiu dela
        if ($origin === ConnectRequest::ORIGIN_AGENDA) {
            return redirect()->route('agenda.index');
        }

        return $this->redirectToSettings('connected');
    }

    public function calendars(): JsonResponse
    {
        return $this->response(fn () => [
            'calendars' => $this->googleCalendarService->calendars(Auth::id()),
        ]);
    }

    public function update(UpdateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->googleCalendarService->updatePreferences(
                userId: Auth::id(),
                calendarId: $request->getCalendarId(),
                invitePatient: $request->getInvitePatient(),
                blockBusy: $request->getBlockBusy(),
            );

            return $this->getMessageSuccess();
        });
    }

    public function disconnect(): JsonResponse
    {
        return $this->response(function () {
            $this->googleCalendarService->disconnect(Auth::id());

            return $this->getMessageSuccess();
        });
    }

    private function redirectToSettings(string $status, ?string $message = null): RedirectResponse
    {
        return redirect()->route('settings.index', array_filter([
            'gc' => $status,
            'gc_message' => $message,
        ]));
    }
}
