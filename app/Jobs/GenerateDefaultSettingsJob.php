<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDefaultSettingsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId)
    {

    }

    public function handle(Settings $settings): void
    {
        try {
            $configs = config('settings.settings');
            $now = now();

            $insertData = [];

            foreach ($configs as $feature => $values) {
                foreach ($values as $key => $value) {
                    $insertData[] = [
                        'user_id' => $this->userId,
                        'feature' => $feature,
                        'key' => $key,
                        'value' => is_bool($value) ? (int) $value : $value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            $settings::insert($insertData);
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'data' => [],
                'message' => 'Falha em salvar configurações. Mensagem: '.$t->getMessage(),
                'method' => 'GenerateDefaultSettingsJob->handle',
            ]);
        }
    }
}
