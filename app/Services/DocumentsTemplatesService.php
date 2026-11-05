<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\Documents;

readonly class DocumentsTemplatesService
{
    public function __construct(
        protected Documents $model,
        private PsychologistSettingService $settingService,
    ) {
    }

    public function getDocAndConfig(int $id, int $userId): array
    {
        $doc = $this->getDoc(id: $id, userId: $userId);

        if (! $doc) {
            throw new \DomainException('Documento não encontrado.');
        }

        return [
            'configs' => [
                'settings' => $this->settingService->details(userId: $userId),
            ],
            'view' => $doc->path,
        ];
    }

    private function getDoc(int $id, int $userId): ?Documents
    {
        $key = "doc_{$id}_user_by_{$userId}";
        $existsCache = CacheHelper::get(key: $key);

        if ($existsCache) {
            return $existsCache;
        }

        $cols = [
            'id',
            'path',
        ];

        $exists = $this->model->findById(id: $id, userId: $userId, cols: $cols);

        if ($exists) {
            CacheHelper::put(key: $key, value: $exists, ttl: now()->addMinutes(5));
        }

        return $exists;
    }
}
