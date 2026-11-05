<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\VideoRoomAuthorizations\ApproveRequest;
use App\Services\SessionAuthorizationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class VideoRoomAuthorizationsController extends Controller
{
    public function __construct(private readonly SessionAuthorizationService $sessionAuthorizationService)
    {
    }

    public function show(string $id): View
    {
        return view('video-room-authorizations.show', ['authorization' => $this->sessionAuthorizationService->pendingFor($id, Auth::id())]);
    }

    public function approve(ApproveRequest $request, string $id): JsonResponse
    {
        return $this->response(function () use ($request, $id) {
            $this->sessionAuthorizationService->approve($id, Auth::id(), (string) $request->validated('proof'));

            return ['approved' => true];
        });
    }
}
