<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HealthReceiptSourceEnum;
use App\Enums\HealthReceiptStatusEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Models\HealthReceipt;
use App\Models\Patients;
use App\Models\PaymentOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Recibos do Receita Saúde: os que nascem das cobranças pagas pelo PSI4 e os recebimentos
 * registrados pela psicóloga (Pix ou dinheiro por fora). O PSI4 não fala com a Receita:
 * a psicóloga importa o arquivo gerado em {@see HealthReceiptBatchService} no Carnê-Leão.
 */
class HealthReceiptService
{
    public function __construct(
        private readonly HealthReceipt $healthReceipt,
        private readonly Patients $patients,
        private readonly PaymentOrder $paymentOrder,
        private readonly User $user,
    ) {
    }

    public function paginate(array $params, int $userId, int $limit, ?int $patientId = null): LengthAwarePaginator
    {
        return $this->healthReceipt->paginateByUserWithPatient(
            userId: $userId,
            cols: [
                'id',
                'patient_id',
                'health_receipt_batch_id',
                'source',
                'amount',
                'paid_on',
                'description',
                'status',
                'refunded_at',
            ],
            status: $params['status'] ?? null,
            patientId: $patientId,
            startDate: $params['start_date'] ?? null,
            endDate: $params['end_date'] ?? null,
            perPage: $limit,
        )->withQueryString();
    }

    /**
     * Avisos do topo da tela: CPF da profissional, total a emitir, recibos com o prazo
     * retroativo encerrado e estornos de recibos que já foram para um arquivo.
     *
     * @return array{missing_professional_document: bool, pending: array{count: int, total: float}, overdue_count: int, refunded: \Illuminate\Database\Eloquent\Collection}
     */
    public function overview(User $user, string $timezone): array
    {
        return [
            'missing_professional_document' => ! HealthReceipt::isValidCpf($user->document),
            'pending' => $this->healthReceipt->summarizePending($user->id),
            'overdue_count' => $this->healthReceipt->countPendingPaidUntil($user->id, $this->lastOverdueDate(now($timezone))),
            'refunded' => $this->healthReceipt->listRefundedExported($user->id, 5),
        ];
    }

    /**
     * Último dia de pagamento cujo prazo retroativo já acabou. O recibo de um pagamento do ano A
     * pode ser emitido até o fim de fevereiro de A+1.
     */
    public function lastOverdueDate(Carbon $today): string
    {
        $year = $today->month > 2 ? $today->year - 1 : $today->year - 2;

        return Carbon::create($year, 12, 31)->toDateString();
    }

    public function details(int $id, int $userId): ?HealthReceipt
    {
        return $this->healthReceipt->findByIdAndUser(
            id: $id,
            userId: $userId,
            cols: ['id', 'patient_id', 'source', 'amount', 'paid_on', 'description', 'status'],
        )?->load('patient:id,name,phone');
    }

    public function store(array $data, int $userId): HealthReceipt
    {
        $this->assertPatientFromUser((int) $data['patient_id'], $userId);

        return $this->healthReceipt->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $userId,
            'patient_id' => $data['patient_id'],
            'source' => HealthReceiptSourceEnum::MANUAL->value,
            'amount' => $data['amount'],
            'paid_on' => $data['paid_on'],
            'description' => $data['description'] ?? null,
            'status' => HealthReceiptStatusEnum::PENDING->value,
        ]);
    }

    public function update(int $id, array $data, int $userId): void
    {
        $receipt = $this->findOrFail($id, $userId, ['id', 'source', 'status']);

        if (! $receipt->isEditable()) {
            throw new \DomainException(__('health-receipts.cannot_edit'));
        }

        $this->assertPatientFromUser((int) $data['patient_id'], $userId);

        $this->healthReceipt->updateByIdAndUser(id: $id, userId: $userId, data: [
            'patient_id' => $data['patient_id'],
            'amount' => $data['amount'],
            'paid_on' => $data['paid_on'],
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Só o recebimento registrado pela psicóloga pode ser apagado; o de uma cobrança paga
     * continua existindo e, se ela não quiser emitir, fica como "Não emitir".
     */
    public function delete(int $id, int $userId): void
    {
        $receipt = $this->findOrFail($id, $userId, ['id', 'source', 'status']);

        if (! $receipt->isEditable()) {
            throw new \DomainException(__('health-receipts.cannot_delete'));
        }

        $receipt->delete();
    }

    /**
     * "Não emitir": tira o recibo da lista de emissão. A obrigação de emitir é da profissional,
     * por isso a tela avisa antes; o recibo pode voltar depois com {@see restore()}.
     */
    public function skip(int $id, int $userId): void
    {
        $receipt = $this->findOrFail($id, $userId, ['id', 'status']);

        if ($receipt->status !== HealthReceiptStatusEnum::PENDING) {
            throw new \DomainException(__('health-receipts.cannot_skip'));
        }

        $this->healthReceipt->updateByIdAndUser(id: $id, userId: $userId, data: [
            'status' => HealthReceiptStatusEnum::CANCELLED->value,
        ]);
    }

    public function restore(int $id, int $userId): void
    {
        $receipt = $this->findOrFail($id, $userId, ['id', 'status', 'refunded_at']);

        // Recibo cancelado por estorno não volta: o pagamento foi devolvido
        if ($receipt->status !== HealthReceiptStatusEnum::CANCELLED || $receipt->refunded_at) {
            throw new \DomainException(__('health-receipts.cannot_restore'));
        }

        $this->healthReceipt->updateByIdAndUser(id: $id, userId: $userId, data: [
            'status' => HealthReceiptStatusEnum::PENDING->value,
        ]);
    }

    /**
     * Cria o recibo da cobrança paga. Idempotente: o Mercado Pago reenvia notificações e o
     * `payment_order_id` é único. A data do pagamento vai no fuso da psicóloga, senão um Pix
     * pago na noite de 31/12 cairia no ano seguinte.
     */
    public function createFromPaymentOrder(int $paymentOrderId): void
    {
        $order = $this->paymentOrder->findById($paymentOrderId, ['id', 'user_id', 'patient_id', 'amount', 'description', 'status', 'paid_at']);

        if (! $order || $order->status !== PaymentOrderStatusEnum::PAID || ! $order->paid_at) {
            return;
        }

        if ($this->healthReceipt->findByPaymentOrder($order->id, ['id'])) {
            return;
        }

        $timezone = $this->user->findById($order->user_id)?->timezone ?: config('app.timezone');

        try {
            $this->healthReceipt->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $order->user_id,
                'patient_id' => $order->patient_id,
                'payment_order_id' => $order->id,
                'source' => HealthReceiptSourceEnum::PAYMENT_ORDER->value,
                'amount' => $order->amount,
                'paid_on' => $order->paid_at->copy()->timezone($timezone)->toDateString(),
                'description' => $order->description,
                'status' => HealthReceiptStatusEnum::PENDING->value,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Outra notificação criou o recibo ao mesmo tempo
        }
    }

    /**
     * Estorno: o recibo ainda não emitido deixa de ser emitido; o que já foi para um arquivo
     * fica marcado para a tela avisar a psicóloga de cancelá-lo no e-CAC (o PSI4 não cancela
     * nada na Receita).
     */
    public function handleRefund(int $paymentOrderId): void
    {
        $receipt = $this->healthReceipt->findByPaymentOrder($paymentOrderId, ['id', 'user_id', 'status', 'refunded_at']);

        if (! $receipt || $receipt->refunded_at) {
            return;
        }

        $data = ['refunded_at' => now()];

        if ($receipt->status === HealthReceiptStatusEnum::PENDING) {
            $data['status'] = HealthReceiptStatusEnum::CANCELLED->value;
        }

        $this->healthReceipt->updateByIdAndUser(id: $receipt->id, userId: $receipt->user_id, data: $data);
    }

    private function findOrFail(int $id, int $userId, array $cols): HealthReceipt
    {
        $receipt = $this->healthReceipt->findByIdAndUser(id: $id, userId: $userId, cols: $cols);

        if (! $receipt) {
            throw new \DomainException(__('health-receipts.not_found'));
        }

        return $receipt;
    }

    private function assertPatientFromUser(int $patientId, int $userId): void
    {
        if (! $this->patients->findById(id: $patientId, userId: $userId, cols: ['id'])) {
            throw new \DomainException(__('health-receipts.patient_not_found'));
        }
    }
}
