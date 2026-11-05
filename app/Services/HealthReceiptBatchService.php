<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccessLogActionEnum;
use App\Enums\HealthReceiptBatchStatusEnum;
use App\Enums\HealthReceiptStatusEnum;
use App\Models\HealthReceipt;
use App\Models\HealthReceiptBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Arquivos de importação da escrituração no Carnê-Leão (e-CAC → Carnê-Leão → Escrituração →
 * Importar Escrituração), no layout de recibos do Receita Saúde publicado pela Receita Federal.
 */
class HealthReceiptBatchService
{
    /** Limite de linhas por arquivo aceito pelo Carnê-Leão. */
    public const MAX_LINES = 1000;

    private const INCOME_CODE = 'R01.001.001';

    private const OCCUPATION_PSYCHOLOGIST = '255';

    public function __construct(
        private readonly HealthReceipt $healthReceipt,
        private readonly HealthReceiptBatch $healthReceiptBatch,
        private readonly AccessLogService $accessLogService,
    ) {
    }

    public function paginate(int $userId, int $limit): LengthAwarePaginator
    {
        return $this->healthReceiptBatch->paginateByUser(
            userId: $userId,
            cols: ['id', 'year', 'receipts_count', 'total_amount', 'status', 'imported_at', 'undone_at', 'created_at'],
            perPage: $limit,
        )->withQueryString();
    }

    /**
     * Gera um ou mais arquivos com os recibos a emitir do período. Recibos com pendência
     * (ex.: paciente sem CPF) ficam de fora e voltam contados em `skipped`.
     *
     * @return array{batches: HealthReceiptBatch[], skipped: int}
     */
    public function generate(array $params, User $user): array
    {
        if (! HealthReceipt::isValidCpf($user->document)) {
            throw new \DomainException(__('health-receipts.professional_without_document'));
        }

        $startDate = Carbon::parse($params['start_date']);
        $endDate = Carbon::parse($params['end_date']);

        if ($startDate->year !== $endDate->year) {
            throw new \DomainException(__('health-receipts.different_years'));
        }

        $result = DB::transaction(function () use ($params, $user, $startDate, $endDate) {
            $receipts = $this->healthReceipt->lockPendingByPeriod(
                userId: $user->id,
                startDate: $startDate->toDateString(),
                endDate: $endDate->toDateString(),
                patientId: $params['patient_id'] ?? null,
            );

            $ready = $receipts->filter(fn (HealthReceipt $receipt) => empty($receipt->issues()));

            if ($ready->isEmpty()) {
                throw new \DomainException(__('health-receipts.nothing_to_export'));
            }

            $batches = [];

            foreach ($ready->chunk(self::MAX_LINES) as $chunk) {
                $batches[] = $this->createBatch($chunk, $user->id, $startDate->year);
            }

            return ['batches' => $batches, 'skipped' => $receipts->count() - $ready->count()];
        });

        $this->accessLogService->register(userId: $user->id, action: AccessLogActionEnum::HEALTH_RECEIPTS_EXPORTED);

        return $result;
    }

    /**
     * Monta de novo o CSV de um arquivo já gerado, a partir dos CPFs gravados na exportação.
     *
     * @return array{filename: string, content: string, imported: bool}
     */
    public function download(int $id, User $user, bool $log = true): array
    {
        $batch = $this->findOrFail($id, $user->id);

        $receipts = $this->healthReceipt->listByBatch(
            batchId: $batch->id,
            userId: $user->id,
            cols: ['id', 'amount', 'paid_on', 'description', 'payer_document', 'beneficiary_document'],
        );

        if ($log) {
            $this->accessLogService->register(userId: $user->id, action: AccessLogActionEnum::HEALTH_RECEIPTS_DOWNLOADED);
        }

        return [
            'filename' => 'receita-saude-'.$batch->year.'-arquivo-'.$batch->id.'.csv',
            'content' => $this->buildCsv($receipts->all(), (string) $user->document),
            'imported' => $batch->status === HealthReceiptBatchStatusEnum::IMPORTED,
        ];
    }

    public function markImported(int $id, int $userId): void
    {
        $batch = $this->findOrFail($id, $userId);

        if ($batch->status !== HealthReceiptBatchStatusEnum::GENERATED) {
            throw new \DomainException(__('health-receipts.cannot_mark_imported'));
        }

        $this->healthReceiptBatch->updateByIdAndUser(id: $id, userId: $userId, data: [
            'status' => HealthReceiptBatchStatusEnum::IMPORTED->value,
            'imported_at' => now(),
        ]);
    }

    /**
     * Desfaz o arquivo no PSI4 (ex.: a psicóloga usou "Desfazer escrituração" no e-CAC ou não
     * chegou a importar). Os recibos voltam a ser emitidos, exceto os estornados nesse meio-tempo.
     */
    public function undo(int $id, int $userId): void
    {
        $batch = $this->findOrFail($id, $userId);

        if ($batch->status === HealthReceiptBatchStatusEnum::UNDONE) {
            throw new \DomainException(__('health-receipts.cannot_undo'));
        }

        $release = [
            'health_receipt_batch_id' => null,
            'payer_document' => null,
            'beneficiary_document' => null,
        ];

        DB::transaction(function () use ($id, $userId, $release) {
            $this->healthReceipt->updateByBatch(batchId: $id, userId: $userId, refunded: false, data: $release + [
                'status' => HealthReceiptStatusEnum::PENDING->value,
            ]);

            $this->healthReceipt->updateByBatch(batchId: $id, userId: $userId, refunded: true, data: $release + [
                'status' => HealthReceiptStatusEnum::CANCELLED->value,
            ]);

            $this->healthReceiptBatch->updateByIdAndUser(id: $id, userId: $userId, data: [
                'status' => HealthReceiptBatchStatusEnum::UNDONE->value,
                'undone_at' => now(),
            ]);
        });
    }

    /**
     * Layout "Recibos do Receita Saúde" do Carnê-Leão: sem cabeçalho, campos separados por
     * ponto e vírgula e codificação ISO-8859-1 (a mesma do modelo oficial). Os 16 campos ficam
     * sempre presentes, mesmo vazios, senão a Receita rejeita a linha.
     *
     * @param  HealthReceipt[]  $receipts
     */
    public function buildCsv(array $receipts, string $professionalDocument): string
    {
        $lines = array_map(fn (HealthReceipt $receipt) => implode(';', [
            $receipt->paid_on->format('d/m/Y'),
            self::INCOME_CODE,
            self::OCCUPATION_PSYCHOLOGIST,
            number_format((float) $receipt->amount, 2, ',', ''),
            '', // valor da dedução
            $this->sanitizeDescription($receipt->description),
            'PF',
            $receipt->payer_document,
            $receipt->beneficiary_document,
            '', // indicador de CPF não informado
            '', // CNPJ
            '', // indicador de IRRF
            '', // valor do IRRF
            'S', // gera o recibo
            $professionalDocument,
            '', // registro profissional: opcional para quem tem um só registro ativo
        ]), $receipts);

        return mb_convert_encoding(implode("\r\n", $lines)."\r\n", 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Descrição genérica, sem conteúdo clínico: ponto e vírgula e quebras de linha quebrariam o
     * layout, e a Receita aceita até 255 caracteres.
     */
    public function sanitizeDescription(?string $description): string
    {
        $description = trim((string) preg_replace('/[;\s]+/u', ' ', (string) $description));

        return Str::limit($description !== '' ? $description : __('health-receipts.default_description'), 255, '');
    }

    /**
     * Grava os CPFs no recibo (o arquivo baixado de novo sai igual ao enviado, mesmo que o
     * cadastro do paciente mude) e vincula os recibos ao arquivo.
     *
     * @param  \Illuminate\Support\Collection<int, HealthReceipt>  $receipts
     */
    private function createBatch($receipts, int $userId, int $year): HealthReceiptBatch
    {
        $batch = $this->healthReceiptBatch->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $userId,
            'year' => $year,
            'receipts_count' => $receipts->count(),
            'total_amount' => $receipts->sum(fn (HealthReceipt $receipt) => (float) $receipt->amount),
            'status' => HealthReceiptBatchStatusEnum::GENERATED->value,
        ]);

        // Um update por paciente: todos os recibos dele levam os mesmos CPFs
        foreach ($receipts->groupBy('patient_id') as $patientReceipts) {
            $first = $patientReceipts->first();

            $this->healthReceipt->updateByIds(
                ids: $patientReceipts->pluck('id')->all(),
                userId: $userId,
                data: [
                    'health_receipt_batch_id' => $batch->id,
                    'payer_document' => $first->resolvePayerDocument(),
                    'beneficiary_document' => $first->patient->document,
                    'status' => HealthReceiptStatusEnum::EXPORTED->value,
                ],
            );
        }

        return $batch;
    }

    private function findOrFail(int $id, int $userId): HealthReceiptBatch
    {
        $batch = $this->healthReceiptBatch->findByIdAndUser(id: $id, userId: $userId, cols: ['id', 'year', 'status']);

        if (! $batch) {
            throw new \DomainException(__('health-receipts.batch_not_found'));
        }

        return $batch;
    }
}
