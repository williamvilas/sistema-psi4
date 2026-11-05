<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgendaStatusEnum;
use App\Enums\AgendaTypeEnum;
use App\Enums\NotificationTypeEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Agenda extends Model
{
    use SoftDeletes;

    protected $table = 'agenda';

    protected $fillable = [
        'user_id',
        'patient_id',
        'service_id',
        'type',
        'scheduled_on',
        'ends_at',
        'room_link',
        'google_event_id',
        'google_synced_at',
        'attendance_source',
        'status',
        'price',
        'notes',
    ];

    protected $casts = [
        'scheduled_on' => 'datetime',
        'ends_at' => 'datetime',
        'reminder_at' => 'datetime',
        'google_synced_at' => 'datetime',
        'price' => 'decimal:2',
        'status' => AgendaStatusEnum::class,
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class)->withTrashed();
    }

    /**
     * Agendada/confirmada e ainda não terminou.
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen() && ! $this->ends_at->isPast();
    }

    /**
     * Agendada/confirmada, mas o horário já passou sem a presença ser registrada.
     */
    public function isPendingAttendance(): bool
    {
        return $this->status->isOpen() && $this->ends_at->isPast();
    }

    public function canMarkAttendance(): bool
    {
        return $this->isPendingAttendance() || in_array($this->status, [AgendaStatusEnum::NO_SHOW, AgendaStatusEnum::COMPLETED], true);
    }

    public function isInProgress(): bool
    {
        return $this->isOpen() && $this->scheduled_on->isPast();
    }

    /**
     * Minutos até o início, quando a sessão começa em até $withinMinutes; null caso contrário.
     */
    public function startsInMinutes(int $withinMinutes = 30): ?int
    {
        if (! $this->isOpen() || $this->scheduled_on->isPast()) {
            return null;
        }

        $minutes = (int) ceil(now()->diffInSeconds($this->scheduled_on) / 60);

        return $minutes <= $withinMinutes ? $minutes : null;
    }

    public function findByIdWithPatientAndUser(int $id, array $cols): ?self
    {
        return $this->newQuery()
            ->select($cols)
            ->with(['user:id,name,whatsapp,email,timezone,current_plan', 'patient:id,name,phone,email,whatsapp_notifications'])
            ->find($id);
    }

    public function findIdAt(int $userId, Carbon $scheduledOnUtc): ?int
    {
        $id = $this->newQuery()
            ->where('user_id', $userId)
            ->where('scheduled_on', $scheduledOnUtc)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Ids das consultas em aberto e ainda não iniciadas com o lembrete vencido.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function dueReminderIds(Carbon $nowUtc, int $afterId, int $limit): Collection
    {
        return $this->newQuery()
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', $nowUtc)
            ->whereIn('status', [AgendaStatusEnum::SCHEDULED->value, AgendaStatusEnum::CONFIRMED->value])
            ->where('scheduled_on', '>', $nowUtc)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
    }

    /**
     * Ids das consultas online em aberto que começam na janela informada e ainda sem o link da sala enviado.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function startingOnlineIdsWithoutRoomLinkNotification(Carbon $fromUtc, Carbon $toUtc, int $afterId, int $limit): Collection
    {
        return $this->newQuery()
            ->where('type', AgendaTypeEnum::ONLINE->value)
            ->whereIn('status', [AgendaStatusEnum::SCHEDULED->value, AgendaStatusEnum::CONFIRMED->value])
            ->where('scheduled_on', '>', $fromUtc)
            ->where('scheduled_on', '<=', $toUtc)
            ->whereNotExists(fn ($query) => $query->select('id')
                ->from('notifications')
                ->whereColumn('notifications.agenda_id', 'agenda.id')
                ->where('notifications.type', NotificationTypeEnum::AGENDA_ROOM_LINK->value)
                ->whereNotNull('notifications.dedupe_key'))
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
    }

    /**
     * Consultas em aberto que ainda vão começar, para recalcular o lembrete após mudar as configurações.
     */
    public function listOpenFromByUser(int $userId, Carbon $fromUtc, array $cols): EloquentCollection
    {
        return $this->newQuery()
            ->select($cols)
            ->where('user_id', $userId)
            ->whereIn('status', [AgendaStatusEnum::SCHEDULED->value, AgendaStatusEnum::CONFIRMED->value])
            ->where('scheduled_on', '>', $fromUtc)
            ->get();
    }

    /**
     * Atualização silenciosa (sem eventos do model) do momento do lembrete.
     */
    public function updateReminderAtQuietly(int $id, ?Carbon $reminderAtUtc): void
    {
        $this->newQuery()->whereKey($id)->update(['reminder_at' => $reminderAtUtc]);
    }

    /**
     * Paciente confirmou a presença pelo botão do lembrete. Update via model para o observer limpar o cache.
     */
    public function confirmByPatient(int $id): bool
    {
        $agenda = $this->newQuery()
            ->whereKey($id)
            ->where('status', AgendaStatusEnum::SCHEDULED->value)
            ->where('scheduled_on', '>', now())
            ->first();

        return $agenda !== null && $agenda->update(['status' => AgendaStatusEnum::CONFIRMED->value]);
    }

    public function findByIdForGoogleSync(int $id, array $cols): ?self
    {
        return $this->newQuery()
            ->select($cols)
            ->with(['user:id,name,timezone', 'patient:id,name,email'])
            ->find($id);
    }

    /**
     * Atualização silenciosa (sem eventos do model) dos dados do evento Google, evitando loop com o observer.
     */
    public function updateGoogleEventQuietly(int $id, array $attributes): void
    {
        $this->newQuery()->whereKey($id)->update($attributes);
    }

    /**
     * Grava o link da sala só se a consulta ainda estiver sem link (silencioso, sem eventos do model).
     */
    public function assignRoomLinkIfEmpty(int $id, string $roomLink): bool
    {
        return $this->newQuery()->whereKey($id)->whereNull('room_link')->update(['room_link' => $roomLink]) > 0;
    }

    public function roomLinkAt(int $userId, Carbon $scheduledOnUtc): ?string
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->where('scheduled_on', $scheduledOnUtc)
            ->value('room_link');
    }

    /**
     * Períodos (início/fim em UTC) de consultas que ocupam a agenda e tocam a janela informada.
     *
     * @return Collection<int, array{start: mixed, end: mixed}>
     */
    public function busyPeriods(int $userId, Carbon $startUtc, Carbon $endUtc): Collection
    {
        return $this->newQuery()
            ->withTrashed()
            ->where('user_id', $userId)
            ->where('status', '!=', AgendaStatusEnum::CANCELED->value)
            ->where('scheduled_on', '<=', $endUtc)
            ->where('ends_at', '>=', $startUtc)
            ->get(['scheduled_on', 'ends_at'])
            ->map(fn ($row) => ['start' => $row->scheduled_on, 'end' => $row->ends_at]);
    }

    /**
     * Se o psicólogo já usou a agenda alguma vez (inclui cancelados e apagados).
     */
    public function existsByUser(int $userId): bool
    {
        return $this->newQuery()
            ->withTrashed()
            ->where('user_id', $userId)
            ->exists();
    }

    public function cancel(int $userId, int $id): bool
    {
        $agenda = $this->newQuery()
            ->where('user_id', $userId)
            ->where('id', $id)
            ->whereIn('status', [AgendaStatusEnum::SCHEDULED->value, AgendaStatusEnum::CONFIRMED->value])
            ->first();

        // Update via model (e não em massa) para o observer disparar a notificação de cancelamento
        return $agenda !== null && $agenda->update(['status' => AgendaStatusEnum::CANCELED->value]);
    }

    /**
     * Só consultas em aberto e já encerradas podem ter a presença informada.
     * Manualmente também é possível corrigir uma presença já apurada (compareceu <-> faltou).
     */
    public function confirmAttendance(int $userId, int $id, AgendaStatusEnum $status, string $source = 'manual'): bool
    {
        $allowedStatuses = [AgendaStatusEnum::SCHEDULED->value, AgendaStatusEnum::CONFIRMED->value];

        if ($source === 'manual') {
            $allowedStatuses[] = AgendaStatusEnum::NO_SHOW->value;
            $allowedStatuses[] = AgendaStatusEnum::COMPLETED->value;
        }

        $agenda = $this->newQuery()
            ->where('user_id', $userId)
            ->where('id', $id)
            ->whereIn('status', $allowedStatuses)
            ->where('ends_at', '<=', now())
            ->first();

        // Update via model (e não em massa) para o observer limpar o cache e notificar o paciente
        return $agenda !== null && $agenda->update(['status' => $status->value, 'attendance_source' => $source]);
    }

    /**
     * Ids das consultas em aberto encerradas antes do limite informado, para o psicólogo informar a presença.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function pendingAttendanceIds(Carbon $endedBeforeUtc, int $afterId, int $limit): Collection
    {
        return $this->newQuery()
            ->whereIn('status', [AgendaStatusEnum::SCHEDULED->value, AgendaStatusEnum::CONFIRMED->value])
            ->where('ends_at', '<=', $endedBeforeUtc)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
    }

    /**
     * Reaproveita o registro cancelado do horário (o unique user_id + scheduled_on impede um novo insert).
     */
    public function recycleCanceled(int $userId, Carbon $scheduledOnUtc, array $attributes): ?self
    {
        $canceled = $this->newQuery()
            ->withTrashed()
            ->where('user_id', $userId)
            ->where('scheduled_on', $scheduledOnUtc)
            ->where('status', AgendaStatusEnum::CANCELED->value)
            ->first();

        if (! $canceled) {
            return null;
        }

        $canceled->restore();
        $canceled->update($attributes);

        return $canceled;
    }

    /**
     * Consulta com a psicóloga e o paciente (nome, CPF e telefone) para montar os participantes da sala de vídeo.
     */
    public function findForVideoRoom(int $id, array $cols): ?self
    {
        return $this->newQuery()
            ->select($cols)
            ->with(['user:id,name,document,whatsapp', 'patient:id,name,document,phone'])
            ->find($id);
    }

    /**
     * Ids das consultas online em aberto e ainda não encerradas, por paciente ou por psicóloga.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function openOnlineIdsBy(string $column, int $value): Collection
    {
        return $this->newQuery()
            ->where($column, $value)
            ->where('type', AgendaTypeEnum::ONLINE->value)
            ->whereIn('status', [AgendaStatusEnum::SCHEDULED->value, AgendaStatusEnum::CONFIRMED->value])
            ->where('ends_at', '>=', now())
            ->pluck('id');
    }

    /**
     * Com $pendingAttendance lista só as consultas já encerradas sem presença apurada (o status é ignorado).
     */
    public function paginateByFilters(
        array $cols,
        int $userId,
        ?int $patientId = null,
        ?string $status = null,
        ?string $type = null,
        ?Carbon $start = null,
        ?Carbon $end = null,
        int $perPage = 10,
        bool $pendingAttendance = false,
    ) {
        return $this->newQuery()
            ->select($cols)
            ->where('user_id', $userId)
            ->when($pendingAttendance, fn ($q) => $q
                ->whereIn('status', [AgendaStatusEnum::SCHEDULED->value, AgendaStatusEnum::CONFIRMED->value])
                ->where('ends_at', '<=', now()))
            ->when(! $pendingAttendance && $status, fn ($q) => $q->where('status', $status))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($start, fn ($q) => $q->where('scheduled_on', '>=', $start))
            ->when($end, fn ($q) => $q->where('scheduled_on', '<=', $end))
            ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
            ->with(['patient:id,name,phone'])
            ->orderBy('scheduled_on', 'asc')
            ->paginate($perPage);
    }

    public function byPatient(
        array $cols,
        int $userId,
        int $patientId,
        ?Carbon $start = null,
        ?Carbon $end = null,
        int $perPage = 10,
        ?AgendaStatusEnum $status = null,
    ) {
        return $this->newQuery()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($start, function ($query) use ($start) {
                $query->where('scheduled_on', '>=', $start);
            })
            ->where('scheduled_on', '<=', $end ?? now('UTC'))
            ->orderBy('scheduled_on', 'desc')
            ->paginate($perPage);
    }

    /**
     * Sessões já ocorridas do paciente (realizadas, faltas e cancelamentos), da mais antiga para a mais recente.
     */
    public function listClosedByPatient(int $userId, int $patientId, ?Carbon $sinceUtc, Carbon $untilUtc): EloquentCollection
    {
        return $this->newQuery()
            ->select(['status', 'scheduled_on'])
            ->where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->whereIn('status', [AgendaStatusEnum::COMPLETED->value, AgendaStatusEnum::NO_SHOW->value, AgendaStatusEnum::CANCELED->value])
            ->when($sinceUtc, fn ($query) => $query->where('scheduled_on', '>=', $sinceUtc))
            ->where('scheduled_on', '<=', $untilUtc)
            ->orderBy('scheduled_on')
            ->get();
    }

    public function firstCompletedByPatient(int $userId, int $patientId): ?self
    {
        return $this->newQuery()
            ->select(['id', 'scheduled_on'])
            ->where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->where('status', AgendaStatusEnum::COMPLETED)
            ->orderBy('scheduled_on')
            ->first();
    }

    /**
     * @return array<string, int> total de agendamentos do período por status (valor do enum)
     */
    public function countByStatusBetween(int $userId, Carbon $startUtc, Carbon $endUtc): array
    {
        return $this->newQuery()
            ->selectRaw('status, COUNT(*) AS total')
            ->where('user_id', $userId)
            ->whereBetween('scheduled_on', [$startUtc, $endUtc])
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status->value => (int) $row->total])
            ->all();
    }

    public function sumPriceByStatusBetween(int $userId, AgendaStatusEnum $status, Carbon $startUtc, Carbon $endUtc): float
    {
        return (float) $this->newQuery()
            ->where('user_id', $userId)
            ->where('status', $status->value)
            ->whereBetween('scheduled_on', [$startUtc, $endUtc])
            ->sum('price');
    }

    /**
     * Pacientes com mais faltas no período, com as faltas, cancelamentos e o total de sessões finalizadas de cada um.
     */
    public function topPatientsByNoShowBetween(int $userId, Carbon $startUtc, Carbon $endUtc, int $limit): EloquentCollection
    {
        return $this->newQuery()
            ->select('patient_id')
            ->selectRaw('SUM(status = ?) AS no_show', [AgendaStatusEnum::NO_SHOW->value])
            ->selectRaw('SUM(status = ?) AS canceled', [AgendaStatusEnum::CANCELED->value])
            ->selectRaw('COUNT(*) AS total')
            ->with('patient:id,name')
            ->where('user_id', $userId)
            ->whereIn('status', [AgendaStatusEnum::COMPLETED->value, AgendaStatusEnum::NO_SHOW->value, AgendaStatusEnum::CANCELED->value])
            ->whereBetween('scheduled_on', [$startUtc, $endUtc])
            ->groupBy('patient_id')
            ->having('no_show', '>', 0)
            ->orderByDesc('no_show')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  AgendaStatusEnum[]  $statuses
     */
    public function listByStatusesBetween(int $userId, array $statuses, Carbon $startUtc, Carbon $endUtc, array $cols): EloquentCollection
    {
        return $this->newQuery()
            ->select($cols)
            ->where('user_id', $userId)
            ->whereIn('status', array_map(fn (AgendaStatusEnum $status) => $status->value, $statuses))
            ->whereBetween('scheduled_on', [$startUtc, $endUtc])
            ->get();
    }

    public function queryBetweenWithPatient(int $userId, Carbon $startUtc, Carbon $endUtc, array $cols): Builder
    {
        return $this->newQuery()
            ->select($cols)
            ->with('patient:id,name')
            ->where('user_id', $userId)
            ->whereBetween('scheduled_on', [$startUtc, $endUtc])
            ->orderBy('scheduled_on');
    }

    /**
     * Sessões não canceladas do período, com o paciente (nome e telefone), em ordem de horário.
     */
    public function listNotCanceledBetweenWithPatient(int $userId, Carbon $startUtc, Carbon $endUtc, array $cols): EloquentCollection
    {
        return $this->queryBetweenWithPatient($userId, $startUtc, $endUtc, $cols)
            ->where('status', '!=', AgendaStatusEnum::CANCELED->value)
            ->get();
    }

    /**
     * Pacientes cuja última sessão finalizada ocorreu entre as duas datas e que não têm sessão futura em aberto.
     *
     * @return Collection<int, object{patient_id: int, last_session_on: string}>
     */
    public function listInactivePatients(int $userId, Carbon $lastAfterUtc, Carbon $lastBeforeUtc, Carbon $nowUtc): Collection
    {
        return $this->newQuery()
            ->selectRaw('patient_id, MAX(scheduled_on) AS last_session_on')
            ->where('user_id', $userId)
            ->where('status', AgendaStatusEnum::COMPLETED->value)
            ->whereHas('patient')
            ->whereNotIn('patient_id', fn ($query) => $query->select('patient_id')
                ->from($this->getTable())
                ->where('user_id', $userId)
                ->whereIn('status', [AgendaStatusEnum::SCHEDULED->value, AgendaStatusEnum::CONFIRMED->value])
                ->where('scheduled_on', '>', $nowUtc)
                ->whereNull('deleted_at'))
            ->groupBy('patient_id')
            ->havingRaw('MAX(scheduled_on) BETWEEN ? AND ?', [$lastAfterUtc, $lastBeforeUtc])
            ->orderByDesc('last_session_on')
            ->toBase()
            ->get();
    }

    /**
     * Sessões encerradas (finalizada ou falta) desde a data, da mais recente para a mais antiga.
     */
    public function listClosedSince(int $userId, Carbon $sinceUtc): EloquentCollection
    {
        return $this->newQuery()
            ->select(['patient_id', 'status', 'scheduled_on'])
            ->where('user_id', $userId)
            ->whereIn('status', [AgendaStatusEnum::COMPLETED->value, AgendaStatusEnum::NO_SHOW->value])
            ->where('scheduled_on', '>=', $sinceUtc)
            ->orderByDesc('scheduled_on')
            ->get();
    }

    /**
     * Consultas em aberto (agendada/confirmada) já encerradas, sem a presença apurada.
     */
    /**
     * Sessões já encerradas sem presença apurada; com período informado considera apenas as agendadas nele.
     */
    public function countPendingAttendance(int $userId, Carbon $endedBeforeUtc, ?Carbon $startUtc = null, ?Carbon $endUtc = null): int
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->whereIn('status', [AgendaStatusEnum::SCHEDULED->value, AgendaStatusEnum::CONFIRMED->value])
            ->where('ends_at', '<=', $endedBeforeUtc)
            ->when($startUtc && $endUtc, fn ($q) => $q->whereBetween('scheduled_on', [$startUtc, $endUtc]))
            ->count();
    }

    public function deleteOpenByPatients(int $userId, array $patientIDs): int
    {
        return $this->newQuery()
            ->select('id')
            ->where('user_id', $userId)
            ->whereIn('patient_id', $patientIDs)
            ->where('scheduled_on', '>=', now())
            ->delete();
    }

    public function getAllDeletedCursor()
    {
        return self::query()
            ->onlyTrashed()
            ->select(['id', 'patient_id', 'deleted_at'])
            ->orderBy('id')
            ->cursor();
    }
}
