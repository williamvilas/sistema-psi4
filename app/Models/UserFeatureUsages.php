<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeatureUsageEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserFeatureUsages extends Model
{
    protected $table = 'user_feature_usages';

    protected $fillable = [
        'user_id',
        'feature',
        'year',
        'month',
        'usage_count',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'year' => 'integer',
        'month' => 'integer',
        'usage_count' => 'integer',
    ];

    public function upsert(int $userId, FeatureUsageEnum $feature, int $amount = 1): int
    {
        $now = now();

        return UserFeatureUsages::query()->upsert(
            [
                [
                    'user_id' => $userId,
                    'feature' => $feature->value,
                    'year' => $now->year,
                    'month' => $now->month,
                    'usage_count' => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['user_id', 'feature', 'year', 'month'],
            [
                'usage_count' => DB::raw('usage_count + '.$amount),
                'updated_at' => $now,
            ]
        );
    }

    /**
     * Garante a linha do mês de cada feature (zerada), para o `lockForUpdate`
     * travar até o primeiro uso do mês.
     *
     * @param  FeatureUsageEnum[]  $features
     */
    public function createMissingForMonth(int $userId, array $features, int $year, int $month): void
    {
        $now = now();

        UserFeatureUsages::query()->insertOrIgnore(array_map(fn (FeatureUsageEnum $feature) => [
            'user_id' => $userId,
            'feature' => $feature->value,
            'year' => $year,
            'month' => $month,
            'usage_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $features));
    }

    /**
     * Uso somado das features no mês, travando as linhas até o fim da transação.
     *
     * @param  FeatureUsageEnum[]  $features
     */
    public function sumForUpdate(int $userId, array $features, int $year, int $month): int
    {
        return (int) UserFeatureUsages::query()
            ->where('user_id', $userId)
            ->whereIn('feature', array_map(fn (FeatureUsageEnum $feature) => $feature->value, $features))
            ->where('year', $year)
            ->where('month', $month)
            ->lockForUpdate()
            ->sum('usage_count');
    }

    /**
     * Uso de cada feature no mês.
     *
     * @return array<string, int> feature => usage_count
     */
    public function countsByMonth(int $userId, int $year, int $month): array
    {
        return UserFeatureUsages::query()
            ->where('user_id', $userId)
            ->where('year', $year)
            ->where('month', $month)
            ->pluck('usage_count', 'feature')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
