<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SessionTranscriptions\AcceptRequest;
use App\Services\PlanLimitService;
use App\Services\SessionTranscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SessionTranscriptionsController extends Controller
{
    public function __construct(
        private readonly SessionTranscriptionService $sessionTranscriptionService,
        private readonly PlanLimitService $planLimitService,
    ) {
    }

    public function index(): View
    {
        abort_unless(SessionTranscriptionService::isEnabled(), 404);

        return view('session-transcriptions.index', ['usage' => $this->sessionTranscriptionService->usage(Auth::id())]);
    }

    public function history(): View
    {
        abort_unless(SessionTranscriptionService::isEnabled(), 404);

        return view('session-transcriptions.history');
    }

    public function paginateHistory(): JsonResponse
    {
        return $this->response(fn () => [
            'view' => view('session-transcriptions.history-paginate', ['transcriptions' => $this->sessionTranscriptionService->paginateHistory(Auth::id())])->render(),
        ]);
    }

    public function consent(string $uuid): View
    {
        try {
            return view('session-transcriptions.consent', ['transcription' => $this->sessionTranscriptionService->consentProof($uuid, Auth::id())]);
        } catch (\DomainException) {
            abort(404);
        }
    }

    public function paginate(): JsonResponse
    {
        return $this->response(fn () => [
            'view' => view('session-transcriptions.paginate', ['transcriptions' => $this->sessionTranscriptionService->paginatePending(Auth::id())])->render(),
        ]);
    }

    public function show(string $uuid): View
    {
        abort_unless(SessionTranscriptionService::isEnabled(), 404);

        try {
            $transcription = $this->sessionTranscriptionService->draft($uuid, Auth::id());
        } catch (\DomainException) {
            abort(404);
        }

        return view('session-transcriptions.show', [
            'transcription' => $transcription,
            // Avisa antes de ela revisar: barrar só ao salvar perderia a edição, como em medical-records/create.
            'limitReached' => $this->planLimitService->hasReached(Auth::id(), 'total_medical_records'),
        ]);
    }

    public function note(string $uuid): JsonResponse
    {
        return $this->response(fn () => ['link' => $this->sessionTranscriptionService->noteLink($uuid, Auth::id())]);
    }

    public function accept(AcceptRequest $request, string $uuid): JsonResponse
    {
        return $this->response(function () use ($request, $uuid) {
            $transcription = $this->sessionTranscriptionService->accept($uuid, Auth::id(), (string) $request->validated('notes_path'));

            return ['link' => route('medical-records.show', ['id' => encrypt($transcription->medical_record_id), 'm' => 'created_success'])];
        });
    }

    public function destroy(string $uuid): JsonResponse
    {
        return $this->response(function () use ($uuid) {
            $this->sessionTranscriptionService->reject($uuid, Auth::id());

            return ['link' => route('session-transcriptions.index')];
        });
    }
}
