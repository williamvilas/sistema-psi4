<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HealthReceiptBatchStatusEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Arquivo gerado para a importação da escrituração no Carnê-Leão. O CSV não é guardado:
 * é montado de novo a cada download a partir dos dados gravados nos recibos.
 */
class HealthReceiptBatch extends Model
{
    protected $table = 'health_receipt_batches';

    protected $fillable = [
        'uuid',
        'user_id',
        'year',
        'receipts_count',
        'total_amount',
        'status',
        'imported_at',
        'undone_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'year' => 'integer',
        'receipts_count' => 'integer',
        'total_amount' => 'decimal:2',
        'status' => HealthReceiptBatchStatusEnum::class,
        'imported_at' => 'datetime',
        'undone_at' => 'datetime',
    ];

    public function receipts(): HasMany
    {
        return $this->hasMany(HealthReceipt::class, 'health_receipt_batch_id');
    }

    public function findByIdAndUser(int $id, int $userId, array $cols = ['*']): ?self
    {
        return HealthReceiptBatch::query()->select($cols)->where('id', $id)->where('user_id', $userId)->first();
    }

    public function updateByIdAndUser(int $id, int $userId, array $data): int
    {
        return HealthReceiptBatch::query()->where('id', $id)->where('user_id', $userId)->update($data);
    }

    public function paginateByUser(int $userId, array $cols, int $perPage): LengthAwarePaginator
    {
        return HealthReceiptBatch::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->paginate(perPage: $perPage);
    }
}
