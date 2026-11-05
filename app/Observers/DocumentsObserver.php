<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PaginateCacheContextEnum;
use App\Helpers\PaginateCache;
use App\Models\Documents;

class DocumentsObserver
{
    public function created(Documents $documents): void
    {
        $this->forget($documents);
    }

    public function updated(Documents $documents): void
    {
        $this->forget($documents);
    }

    public function deleted(Documents $documents): void
    {
        $this->forget($documents);
    }

    public function restored(Documents $documents): void
    {
        $this->forget($documents);
    }

    public function forceDeleted(Documents $documents): void
    {
        $this->forget($documents);
    }

    private function forget(Documents $documents): void
    {
        // Template sem dono (user_id null) aparece na listagem de todos os usuários
        if ($documents->user_id === null) {
            PaginateCache::forgetGlobal(PaginateCacheContextEnum::DOCUMENTS);

            return;
        }

        PaginateCache::forget((int) $documents->user_id, PaginateCacheContextEnum::DOCUMENTS);
    }
}
