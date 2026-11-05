<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\VideoRoomAccessDeniedException;
use App\Exceptions\VideoRoomOutsideWindowException;
use App\Http\Requests\VideoRoomAccess\StoreRequest;
use App\Services\JitsiRoomService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint público chamado pelo front do Jitsi: troca o CPF ou o telefone digitado por um JWT do Prosody.
 * Contrato consumido pelo Jitsi: não alterar nomes de campos nem status codes.
 */
class VideoRoomAccessController extends Controller
{
    public function __construct(private readonly JitsiRoomService $jitsiRoomService)
    {
    }

    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $access = $this->jitsiRoomService->access(
                room: (string) $request->validated('room'),
                cpf: $request->validated('cpf'),
                phone: $request->validated('phone'),
            );
        } catch (VideoRoomAccessDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (VideoRoomOutsideWindowException $e) {
            return response()->json(['message' => $e->getMessage()], 410);
        }

        return response()->json([
            'jwt' => $access['jwt'],
            'name' => $access['name'],
            'role' => $access['role'],
            'expires_at' => $access['expires_at']->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
