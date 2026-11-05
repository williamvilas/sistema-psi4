<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HealthReceiptSourceEnum;
use App\Enums\HealthReceiptStatusEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LaravelLegends\PtBrValidator\Rules\Cpf;

/**
 * Recibo do Receita Saúde a ser escriturado no Carnê-Leão. Nasce de uma cobrança paga pelo
 * PSI4 ou de um recebimento registrado pela psicóloga; nunca leva conteúdo clínico.
 */
class HealthReceipt extends Model
{
    use SoftDeletes;

    protected $table = 'health_receipts';

    protected $fillable = [
        'uuid',
        'user_id',
        'patient_id',
        'payment_order_id',
        'health_receipt_batch_id',
        'source',
        'amount',
        'paid_on',
        'description',
        'payer_document',
        'beneficiary_document',
        'status',
        'refunded_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'patient_id' => 'integer',
        'payment_order_id' => 'integer',
        'health_receipt_batch_id' => 'integer',
        'source' => HealthReceiptSourceEnum::class,
        'amount' => 'decimal:2',
        'paid_on' => 'date',
        'status' => HealthReceiptStatusEnum::class,
        'refunded_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(HealthReceiptBatch::class, 'health_receipt_batch_id');
    }

    /**
     * CPF de quem pagou: o do responsável financeiro, quando houver, ou o do próprio paciente.
     */
    public function resolvePayerDocument(): ?string
    {
        return $this->patient?->payer_document ?: $this->patient?->document;
    }

    /**
     * Pendências que impedem o recibo de entrar no arquivo do Carnê-Leão (slugs de health-receipts.issues).
     *
     * @return string[]
     */
    public function issues(): array
    {
        $issues = [];
        $patient = $this->patient;

        if (! self::isValidCpf($patient?->document)) {
            $issues[] = empty($patient?->document) ? 'patient_without_document' : 'patient_invalid_document';
        }

        if (! empty($patient?->payer_document) && ! self::isValidCpf($patient->payer_document)) {
            $issues[] = 'payer_invalid_document';
        }

        if ((float) $this->amount <= 0) {
            $issues[] = 'invalid_amount';
        }

        return $issues;
    }

    public function isEditable(): bool
    {
        return $this->source === HealthReceiptSourceEnum::MANUAL && $this->status === HealthReceiptStatusEnum::PENDING;
    }

    public static function isValidCpf(?string $document): bool
    {
        return ! empty($document) && (new Cpf)->passes('document', $document);
    }

    public function findByIdAndUser(int $id, int $userId, array $cols = ['*']): ?self
    {
        return HealthReceipt::query()->select($cols)->where('id', $id)->where('user_id', $userId)->first();
    }

    public function findByPaymentOrder(int $paymentOrderId, array $cols = ['*']): ?self
    {
        return HealthReceipt::query()->select($cols)->where('payment_order_id', $paymentOrderId)->first();
    }

    public function updateByIdAndUser(int $id, int $userId, array $data): int
    {
        return HealthReceipt::query()->where('id', $id)->where('user_id', $userId)->update($data);
    }

    public function paginateByUserWithPatient(
        int $userId,
        array $cols,
        ?string $status,
        ?int $patientId,
        ?string $startDate,
        ?string $endDate,
        int $perPage,
    ): LengthAwarePaginator {
        return HealthReceipt::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
            ->when($startDate, fn ($q) => $q->where('paid_on', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('paid_on', '<=', $endDate))
            ->with('patient:id,name,document,payer_name,payer_document,deleted_at')
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage);
    }

    /**
     * Recibos a emitir do período, travados para que dois cliques não exportem o mesmo recibo duas vezes.
     * Precisa rodar dentro de uma transação.
     */
    public function lockPendingByPeriod(int $userId, string $startDate, string $endDate, ?int $patientId = null): EloquentCollection
    {
        return HealthReceipt::query()
            ->select(['id', 'patient_id', 'amount', 'paid_on', 'description', 'status'])
            ->where('user_id', $userId)
            ->where('status', HealthReceiptStatusEnum::PENDING->value)
            ->whereBetween('paid_on', [$startDate, $endDate])
            ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
            ->with('patient:id,document,payer_document')
            ->orderBy('paid_on')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function listByBatch(int $batchId, int $userId, array $cols = ['*']): EloquentCollection
    {
        return HealthReceipt::query()
            ->select($cols)
            ->where('health_receipt_batch_id', $batchId)
            ->where('user_id', $userId)
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();
    }

    public function updateByIds(array $ids, int $userId, array $data): int
    {
        return HealthReceipt::query()->whereIn('id', $ids)->where('user_id', $userId)->update($data);
    }

    /**
     * @param  bool|null  $refunded  true: só os estornados; false: só os não estornados; null: todos
     */
    public function updateByBatch(int $batchId, int $userId, array $data, ?bool $refunded = null): int
    {
        return HealthReceipt::query()
            ->where('health_receipt_batch_id', $batchId)
            ->where('user_id', $userId)
            ->when($refunded === true, fn ($q) => $q->whereNotNull('refunded_at'))
            ->when($refunded === false, fn ($q) => $q->whereNull('refunded_at'))
            ->update($data);
    }

    /**
     * @return array{count: int, total: float} recibos ainda a emitir
     */
    public function summarizePending(int $userId): array
    {
        $row = HealthReceipt::query()
            ->selectRaw('COUNT(*) AS total_receipts, COALESCE(SUM(amount), 0) AS total_amount')
            ->where('user_id', $userId)
            ->where('status', HealthReceiptStatusEnum::PENDING->value)
            ->toBase()
            ->first();

        return ['count' => (int) $row->total_receipts, 'total' => (float) $row->total_amount];
    }

    /**
     * Recibos a emitir de pagamentos feitos até a data informada (prazo retroativo já encerrado).
     */
    public function countPendingPaidUntil(int $userId, string $date): int
    {
        return HealthReceipt::query()
            ->where('user_id', $userId)
            ->where('status', HealthReceiptStatusEnum::PENDING->value)
            ->where('paid_on', '<=', $date)
            ->count();
    }

    /**
     * Recibos já colocados em um arquivo cujo pagamento foi estornado depois.
     */
    public function listRefundedExported(int $userId, int $limit): EloquentCollection
    {
        return HealthReceipt::query()
            ->select(['id', 'patient_id', 'amount', 'paid_on', 'refunded_at'])
            ->where('user_id', $userId)
            ->where('status', HealthReceiptStatusEnum::EXPORTED->value)
            ->whereNotNull('refunded_at')
            ->with('patient:id,name')
            ->orderByDesc('refunded_at')
            ->limit($limit)
            ->get();
    }
}
