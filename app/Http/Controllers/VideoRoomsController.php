<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\VideoRooms\StoreRequest;
use App\Http\Requests\VideoRooms\UpdateRequest;
use App\Models\VideoRoom;
use App\Models\VideoRoomParticipant;
use App\Services\JitsiRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * API interna (token de serviço) para criar, consultar, reagendar e cancelar salas de vídeo do Jitsi.
 */
class VideoRoomsController extends Controller
{
    public function __construct(private readonly JitsiRoomService $jitsiRoomService)
    {
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $videoRoom = $this->jitsiRoomService->store($request->validated());

        return response()->json($this->toArray($videoRoom), 201);
    }

    public function show(string $room): JsonResponse
    {
        $videoRoom = $this->jitsiRoomService->findByRoom($room);

        return response()->json($this->toArray($videoRoom, withParticipants: true));
    }

    public function update(UpdateRequest $request, string $room): JsonResponse
    {
        $videoRoom = $this->jitsiRoomService->update($this->jitsiRoomService->findByRoom($room), $request->validated());

        return response()->json($this->toArray($videoRoom, withParticipants: true));
    }

    public function destroy(string $room): Response
    {
        $this->jitsiRoomService->cancel($this->jitsiRoomService->findByRoom($room));

        return response()->noContent();
    }

    private function toArray(VideoRoom $videoRoom, bool $withParticipants = false): array
    {
        $data = [
            'room' => $videoRoom->room,
            'url' => $this->jitsiRoomService->url($videoRoom),
            'starts_at' => $videoRoom->starts_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'ends_at' => $videoRoom->ends_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'status' => $videoRoom->status->value,
        ];

        if ($withParticipants) {
            // Sem telefone nem hash
            $data['participants'] = $videoRoom->participants
                ->map(fn (VideoRoomParticipant $participant) => ['name' => $participant->name, 'role' => $participant->role->value])
                ->values()
                ->all();
        }

        return $data;
    }
}
