<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Logs;
use Illuminate\Support\Facades\DB;

readonly class LogService
{
    public function __construct(private Logs $logs)
    {

    }

    public function store(array $data): void
    {
        try {
            DB::transaction(function () use ($data) {
                $this->logs->create([
                    'user_id' => $data['user_id'] ?? null,
                    'message' => $data['message'] ?? null,
                    'route' => $data['route'] ?? null,
                    'method' => $data['method'] ?? null,
                    'exception' => $data['exception'] ?? null,
                    'data' => $data['data'] ?? null,
                ]);
            });

        } catch (\Throwable $t) {
            throw $t;
        }
    }
}
