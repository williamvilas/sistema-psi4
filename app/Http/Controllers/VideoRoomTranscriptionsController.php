<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SessionTranscriptionConsentEnum;
use App\Exceptions\PlanLimitException;
use App\Exceptions\SessionTranscriptionConflictException;
use App\Http\Middleware\AuthenticateVideoRoomJwt;
use App\Http\Requests\VideoRoomTranscriptions\ConsentRequest;
use App\Http\Requests\VideoRoomTranscriptions\SegmentRequest;
use App\Http\Requests\VideoRoomTranscriptions\StoreRequest;
use App\Models\VideoRoom;
use App\Services\SessionTranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Transcrição chamada pelo front do Jitsi, sem login no PSI4. A psicóloga usa o JWT de moderador; o convidado usa o próprio JWT,
 * e só para ler o texto e registrar a decisão dele.
 */
class VideoRoomTranscriptionsController extends Controller
{
    public function __construct(private readonly SessionTranscriptionService $sessionTranscriptionService)
    {
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json($this->sessionTranscriptionService->context($this->videoRoom($request)));
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->respond(fn () => response()->json([
            'uuid' => $this->sessionTranscriptionService->request($this->videoRoom($request), $request->validated())->uuid,
        ], 201));
    }

    public function consentText(Request $request, string $uuid): JsonResponse
    {
        return $this->respond(fn () => response()->json($this->sessionTranscriptionService->consentText(
            $this->videoRoom($request),
            (string) $request->attributes->get(AuthenticateVideoRoomJwt::PARTICIPANT_REF),
            $uuid,
        )));
    }

    public function consent(ConsentRequest $request, string $uuid): JsonResponse
    {
        return $this->respond(function () use ($request, $uuid) {
            $transcription = $this->sessionTranscriptionService->decide(
                videoRoom: $this->videoRoom($request),
                participantRef: (string) $request->attributes->get(AuthenticateVideoRoomJwt::PARTICIPANT_REF),
                uuid: $uuid,
                decision: SessionTranscriptionConsentEnum::from((string) $request->validated('decision')),
                ip: $request->ip(),
            );

            return response()->json(['status' => $transcription->status->value]);
        });
    }

    public function segment(SegmentRequest $request, string $uuid): JsonResponse
    {
        return $this->respond(fn () => response()->json($this->sessionTranscriptionService->segmentUploadUrl($this->videoRoom($request), $uuid, $request->validated())));
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        return $this->respond(function () use ($request, $uuid) {
            $transcription = $this->sessionTranscriptionService->complete($this->videoRoom($request), $uuid);

            return response()->json(['status' => $transcription->status->value]);
        });
    }

    private function respond(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (PlanLimitException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'quota', 'link' => route('plans.index')], 403);
        } catch (SessionTranscriptionConflictException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'conflict'], 409);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    private function videoRoom(Request $request): VideoRoom
    {
        return $request->attributes->get(AuthenticateVideoRoomJwt::ATTRIBUTE);
    }
}
