<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\PlanLimitException;
use App\Exceptions\SessionActivityPinException;
use App\Http\Middleware\AuthenticateVideoRoomJwt;
use App\Http\Requests\VideoRoomActivities\StoreRequest;
use App\Http\Requests\VideoRoomActivities\UnlockRequest;
use App\Models\VideoRoom;
use App\Services\SessionActivityService;
use App\Services\SessionAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dinâmicas chamadas pelo front do Jitsi, sem login no PSI4. Autenticadas pelo JWT da sala (só moderador).
 */
class VideoRoomActivitiesController extends Controller
{
    public function __construct(
        private readonly SessionActivityService $sessionActivityService,
        private readonly SessionAuthorizationService $sessionAuthorizationService,
    ) {
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json($this->sessionActivityService->context($this->videoRoom($request)));
    }

    public function unlock(UnlockRequest $request): JsonResponse
    {
        return $this->respond(fn () => response()->json(
            $this->sessionActivityService->unlock($this->videoRoom($request), (string) $request->validated('pin'))
        ));
    }

    public function requestAuthorization(Request $request): JsonResponse
    {
        return $this->respond(fn () => response()->json($this->sessionAuthorizationService->request($this->videoRoom($request)), 201));
    }

    public function authorizationResult(Request $request, string $id): JsonResponse
    {
        return $this->respond(fn () => response()->json($this->sessionAuthorizationService->result($this->videoRoom($request), $id)));
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $result = $this->sessionActivityService->store($this->videoRoom($request), $request->validated());

            return response()->json(['uuid' => $result['activity']->uuid], $result['created'] ? 201 : 200);
        });
    }

    private function respond(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (SessionActivityPinException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->locked ? 'pin_locked' : 'pin_invalid'], 403);
        } catch (PlanLimitException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'plan_limit', 'link' => route('plans.index')], 403);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    private function videoRoom(Request $request): VideoRoom
    {
        return $request->attributes->get(AuthenticateVideoRoomJwt::ATTRIBUTE);
    }
}
