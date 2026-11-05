<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgendaTypeEnum;
use App\Enums\VideoRoomRoleEnum;
use App\Models\Agenda;
use App\Models\VideoRoom;

/**
 * Sala de vídeo no Jitsi do PSI4 (meet.psi4.com.br) para as consultas online: só entram a psicóloga
 * (moderadora), pelo CPF, e o paciente, pelo CPF ou, sem ele, pelo telefone, dentro da janela da consulta.
 * O vínculo é o session_id = id da agenda.
 */
readonly class AgendaVideoRoomService
{
    private const AGENDA_COLUMNS = ['id', 'user_id', 'patient_id', 'type', 'status', 'scheduled_on', 'ends_at', 'room_link'];

    public function __construct(
        private Agenda $agenda = new Agenda,
        private VideoRoom $videoRoom = new VideoRoom,
        private CpfHasher $cpfHasher = new CpfHasher,
        private PhoneHasher $phoneHasher = new PhoneHasher,
        private JitsiRoomService $jitsiRoomService = new JitsiRoomService(new CpfHasher, new PhoneHasher, new RoomIdGenerator, new JitsiTokenIssuer),
    ) {
    }

    /**
     * Cria a sala e grava o link na consulta online em aberto que ainda não tem link.
     *
     * @return bool se o link foi gravado
     *
     * @throws \DomainException psicóloga sem CPF válido (sem ela a sala não teria moderadora)
     */
    public function attach(int $agendaId): bool
    {
        $agenda = $this->agenda->findForVideoRoom($agendaId, self::AGENDA_COLUMNS);

        if (! $agenda || ! $agenda->status?->isOpen() || $agenda->type !== AgendaTypeEnum::ONLINE->value || ! empty($agenda->room_link)) {
            return false;
        }

        $videoRoom = $this->jitsiRoomService->store([
            'session_id' => (string) $agenda->id,
            'starts_at' => $agenda->scheduled_on->toIso8601String(),
            'ends_at' => $agenda->ends_at->toIso8601String(),
            'participants' => $this->participants($agenda),
        ]);

        if ($this->agenda->assignRoomLinkIfEmpty($agenda->id, $this->jitsiRoomService->url($videoRoom))) {
            return true;
        }

        // Outro processo gravou um link antes: a sala criada aqui não é usada
        $this->jitsiRoomService->cancel($videoRoom);

        return false;
    }

    /**
     * Acompanha a remarcação da consulta: a janela de acesso da sala segue o novo horário (o link não muda).
     */
    public function reschedule(Agenda $agenda): void
    {
        foreach ($this->videoRoom->activeBySessions([$agenda->id]) as $videoRoom) {
            $this->jitsiRoomService->update($videoRoom, [
                'starts_at' => $agenda->scheduled_on->toIso8601String(),
                'ends_at' => $agenda->ends_at->toIso8601String(),
            ]);
        }
    }

    /**
     * Consulta cancelada ou apagada: a sala deixa de aceitar entradas.
     */
    public function cancel(int $agendaId): void
    {
        foreach ($this->videoRoom->activeBySessions([$agendaId]) as $videoRoom) {
            $this->jitsiRoomService->cancel($videoRoom);
        }
    }

    /**
     * Troca de CPF ou telefone: atualiza quem pode entrar nas salas das consultas ainda por vir.
     */
    public function refreshParticipantsBy(string $column, int $value): void
    {
        $agendaIds = $this->agenda->openOnlineIdsBy($column, $value);
        $videoRooms = $this->videoRoom->activeBySessions($agendaIds->all());

        // Uma consulta por sala: os participantes são montados a partir dela
        foreach ($videoRooms as $videoRoom) {
            $agenda = $this->agenda->findForVideoRoom((int) $videoRoom->session_id, self::AGENDA_COLUMNS);

            if ($agenda) {
                $this->jitsiRoomService->update($videoRoom, ['participants' => $this->participants($agenda)]);
            }
        }
    }

    /**
     * Psicóloga moderadora, sempre pelo CPF, e paciente convidado. Sem CPF, o paciente entra pelo telefone.
     * Dado do paciente inválido (ou igual ao da psicóloga) fica de fora.
     *
     * @return array<int, array{cpf?: string, phone?: string, name: string, role: string}>
     */
    private function participants(Agenda $agenda): array
    {
        $psychologistCpf = $this->cpfHasher->normalize((string) $agenda->user?->document);

        if ($psychologistCpf === null) {
            throw new \DomainException(__('video-room.psychologist_document_required'));
        }

        $participants = [[
            'cpf' => $psychologistCpf,
            'name' => $this->displayName($agenda->user->name),
            'role' => VideoRoomRoleEnum::MODERATOR->value,
        ]];

        $patientCredential = $this->patientCredential($agenda, $psychologistCpf);

        if ($patientCredential !== null) {
            $participants[] = $patientCredential + [
                'name' => $this->displayName($agenda->patient->name),
                'role' => VideoRoomRoleEnum::GUEST->value,
            ];
        }

        return $participants;
    }

    /**
     * @return array{cpf: string}|array{phone: string}|null
     */
    private function patientCredential(Agenda $agenda, string $psychologistCpf): ?array
    {
        $cpf = $this->cpfHasher->normalize((string) $agenda->patient?->document);

        if ($cpf !== null) {
            return $cpf === $psychologistCpf ? null : ['cpf' => $cpf];
        }

        $phone = $this->phoneHasher->normalize((string) $agenda->patient?->phone);

        return $phone === null || $phone === $this->phoneHasher->normalize((string) $agenda->user->whatsapp) ? null : ['phone' => $phone];
    }

    /**
     * Nome exibido na chamada (a coluna aceita até 120 caracteres).
     */
    private function displayName(?string $name): string
    {
        return mb_substr(trim((string) $name), 0, 120);
    }
}
