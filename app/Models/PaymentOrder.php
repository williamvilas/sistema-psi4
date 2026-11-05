<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaginateCacheContextEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentOrderIntegrationStatusEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Helpers\PaginateCache;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentOrder extends Model
{
    use SoftDeletes;

    protected $table = 'payment_orders';

    protected $fillable = [
        'uuid',
        'user_id',
        'patient_id',
        'amount',
        'description',
        'status',
        'integration_status',
        'payment_methods',
        'payment_method',
        'external_id',
        'checkout_url',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'patient_id' => 'integer',
        'amount' => 'decimal:2',
        'status' => PaymentOrderStatusEnum::class,
        'integration_status' => PaymentOrderIntegrationStatusEnum::class,
        'payment_methods' => 'array',
        'payment_method' => PaymentMethodEnum::class,
        'due_date' => 'date',
        'paid_at' => 'datetime',
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
     * Status efetivo da ordem: pendente com vencimento já passado é considerado expirado.
     */
    public function getCurrentStatus(): PaymentOrderStatusEnum
    {
        if ($this->status === PaymentOrderStatusEnum::PENDING && $this->due_date && $this->due_date->endOfDay()->isPast()) {
            return PaymentOrderStatusEnum::EXPIRED;
        }

        return $this->status;
    }

    /**
     * Só ordens pendentes podem ser editadas. Enquanto o checkout está sendo criado no
     * Mercado Pago a edição fica bloqueada, senão o job geraria a cobrança com os dados
     * antigos; já integrada, a edição também atualiza a preference no Mercado Pago.
     */
    public function isEditable(): bool
    {
        return $this->status === PaymentOrderStatusEnum::PENDING
            && $this->integration_status !== PaymentOrderIntegrationStatusEnum::PROCESSING;
    }

    public function findByIdWithPatientEmail(int $id, array $cols = ['*']): ?self
    {
        return PaymentOrder::query()->select($cols)->with(['patient:id,email', 'user:id,name,current_plan'])->where('id', $id)->first();
    }

    public function findByIdAndUserWithPatient(int $id, int $userId, array $cols = ['*'], array $patientCols = ['id', 'name']): ?self
    {
        return PaymentOrder::query()
            ->select($cols)
            ->with('patient:'.implode(',', $patientCols))
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function findByIdAndUser(int $id, int $userId, array $cols = ['*']): ?self
    {
        return PaymentOrder::query()->select($cols)->where('id', $id)->where('user_id', $userId)->first();
    }

    public function updateByIdAndUser(int $id, int $userId, array $data): int
    {
        $updated = PaymentOrder::query()->where('id', $id)->where('user_id', $userId)->update($data);

        // Update em massa não dispara o observer, então o cache é limpo aqui
        PaginateCache::forget($userId, PaginateCacheContextEnum::PAYMENTS);

        return $updated;
    }

    public function deleteInBatch(array $id, int $userId): int
    {
        $deleted = PaymentOrder::query()->whereIn('id', $id)->where('user_id', $userId)->delete();

        // Delete em massa não dispara o observer, então o cache é limpo aqui
        PaginateCache::forget($userId, PaginateCacheContextEnum::PAYMENTS);

        return $deleted;
    }

    public function existsByUser(int $userId): bool
    {
        return PaymentOrder::query()->where('user_id', $userId)->exists();
    }

    public function findById(int $id, array $cols = ['*']): ?self
    {
        return PaymentOrder::query()->select($cols)->where('id', $id)->first();
    }

    public function findByUuid(string $uuid, array $cols = ['*']): ?self
    {
        return PaymentOrder::query()->select($cols)->where('uuid', $uuid)->first();
    }

    /**
     * Total e quantidade das ordens de um status, criadas dentro do período.
     *
     * @return array{total: float, count: int}
     */
    public function summarizeByStatusAndPeriod(int $userId, PaymentOrderStatusEnum $status, Carbon $startUtc, Carbon $endUtc): array
    {
        return $this->summarizeByStatusesAndPeriod($userId, [$status], $startUtc, $endUtc);
    }

    /**
     * Quantidade e total das ordens em qualquer um dos status informados, criadas dentro do período.
     *
     * @param  array<int, PaymentOrderStatusEnum>  $statuses
     * @return array{total: float, count: int}
     */
    public function summarizeByStatusesAndPeriod(int $userId, array $statuses, Carbon $startUtc, Carbon $endUtc): array
    {
        $row = PaymentOrder::query()
            ->selectRaw('COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count')
            ->where('user_id', $userId)
            ->whereIn('status', array_map(fn (PaymentOrderStatusEnum $status): string => $status->value, $statuses))
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->first();

        return ['total' => (float) $row->total, 'count' => (int) $row->count];
    }

    /**
     * Quantidade e total das ordens pagas por meio de pagamento, criadas dentro do período.
     * Ordens sem meio de pagamento informado vêm agrupadas com `method` nulo.
     *
     * @return array<int, array{method: string|null, count: int, total: float}>
     */
    public function summarizePaidByPaymentMethodAndPeriod(int $userId, Carbon $startUtc, Carbon $endUtc): array
    {
        return PaymentOrder::query()
            ->selectRaw('payment_method AS method, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total')
            ->where('user_id', $userId)
            ->where('status', PaymentOrderStatusEnum::PAID->value)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->groupBy('payment_method')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['method' => $row->method === null ? null : (string) $row->method, 'count' => (int) $row->count, 'total' => (float) $row->total])
            ->all();
    }

    /**
     * Data de criação e valor das ordens pagas, criadas dentro do período.
     *
     * @return array<int, array{created_at: Carbon, amount: float}>
     */
    public function listPaidByPeriod(int $userId, Carbon $startUtc, Carbon $endUtc): array
    {
        return PaymentOrder::query()
            ->select(['created_at', 'amount'])
            ->where('user_id', $userId)
            ->where('status', PaymentOrderStatusEnum::PAID->value)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($row) => ['created_at' => $row->created_at, 'amount' => (float) $row->amount])
            ->all();
    }

    /**
     * Ordens pendentes ainda dentro do vencimento (as vencidas contam como expiradas).
     */
    public function countPending(int $userId, string $today): int
    {
        return $this->queryPending($userId, $today)->count();
    }

    /**
     * @return array{count: int, total: float} ordens pendentes ainda dentro do vencimento
     */
    public function summarizePending(int $userId, string $today): array
    {
        $row = $this->queryPending($userId, $today)
            ->selectRaw('COUNT(*) AS total_orders, COALESCE(SUM(amount), 0) AS total_amount')
            ->toBase()
            ->first();

        return ['count' => (int) $row->total_orders, 'total' => (float) $row->total_amount];
    }

    private function queryPending(int $userId, string $today): Builder
    {
        return PaymentOrder::query()
            ->where('user_id', $userId)
            ->where('status', PaymentOrderStatusEnum::PENDING->value)
            ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '>=', $today));
    }

    /**
     * @return array{count: int, total: float} ordens pendentes com vencimento anterior a hoje
     */
    public function summarizeOverdue(int $userId, string $today): array
    {
        $row = $this->queryOverdue($userId, $today)
            ->selectRaw('COUNT(*) AS total_orders, COALESCE(SUM(amount), 0) AS total_amount')
            ->toBase()
            ->first();

        return ['count' => (int) $row->total_orders, 'total' => (float) $row->total_amount];
    }

    public function listOverdue(int $userId, string $today, int $limit): EloquentCollection
    {
        return $this->queryOverdue($userId, $today)
            ->select(['id', 'patient_id', 'amount', 'due_date'])
            ->with('patient:id,name,phone')
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }

    private function queryOverdue(int $userId, string $today): Builder
    {
        return PaymentOrder::query()
            ->where('user_id', $userId)
            ->where('status', PaymentOrderStatusEnum::PENDING->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today);
    }

    public function paginateByUserWithPatient(
        int $userId,
        array $cols,
        ?string $status,
        ?int $patientId,
        string $today,
        int $perPage,
    ): LengthAwarePaginator {
        return PaymentOrder::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->when(! empty($status), function ($q) use ($status, $today) {
                // Expirado não é gravado: é uma pendente com vencimento passado
                if ($status === PaymentOrderStatusEnum::EXPIRED->value) {
                    $q->where('status', PaymentOrderStatusEnum::PENDING->value)->where('due_date', '<', $today);

                    return;
                }

                $q->where('status', $status);

                if ($status === PaymentOrderStatusEnum::PENDING->value) {
                    $q->where(fn ($sub) => $sub->whereNull('due_date')->orWhere('due_date', '>=', $today));
                }
            })
            ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
            ->with('patient:id,name,phone')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage);
    }
}
