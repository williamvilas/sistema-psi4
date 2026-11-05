<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\FeatureUsageEnum;
use App\Models\UserFeatureUsages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SaveFeatureUsageJob implements ShouldQueue
{
    use Queueable;

    // Métrica descartável: o try/catch do handle já registra a falha, sem retry
    public int $tries = 1;

    public function __construct(public int $userId, public FeatureUsageEnum $feature)
    {
        // Só processa após o commit, evitando contar operações revertidas por rollback
        $this->afterCommit();
    }

    public function handle(UserFeatureUsages $featureUsages): void
    {
        // Falha na métrica nunca deve interromper a ação do usuário
        try {
            $featureUsages->upsert($this->userId, $this->feature);
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => 'Falha ao salvar o uso da feature: '.$t->getMessage(),
                'data' => ['user_id' => $this->userId, 'feature' => $this->feature->value],
            ]);
        }
    }
}
