<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentsEnum;
use App\Enums\PaginateCacheContextEnum;
use App\Helpers\PaginateCache;
use App\Models\Documents;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

readonly class DocumentsService
{
    public function __construct(private Documents $documentTemplates)
    {
    }

    public function getDoc(int $id, int $userId): ?Documents
    {
        $cols = [
            'id',
            'user_id',
            'title',
            'path',
            'type',
        ];

        return $this->documentTemplates
            ->newQuery()
            ->select($cols)
            ->where('id', $id)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereNull('user_id');
            })
            ->first();
    }

    public function paginate(array $params, int $limit, int $userId): LengthAwarePaginator
    {
        return PaginateCache::remember(
            context: PaginateCacheContextEnum::DOCUMENTS,
            userId: $userId,
            params: ['q' => $params['q'] ?? null, 'type' => $params['type'] ?? null, 'limit' => $limit],
            callback: fn () => $this->queryPaginate($params, $limit, $userId),
            withGlobal: true,
        );
    }

    private function queryPaginate(array $params, int $limit, int $userId): LengthAwarePaginator
    {
        $cols = [
            'id',
            'title',
            'type',
        ];

        $q = $this->documentTemplates->newQuery()->select($cols);

        if (! empty($params['q'])) {
            $types = $this->typesMatching($params['q']);

            $q->where(function ($qb) use ($params, $types) {
                $qb->where('title', 'LIKE', '%'.$params['q'].'%');

                if ($types) {
                    $qb->orWhereIn('type', $types);
                }
            });
        }

        if (! empty($params['type'])) {
            $q->where('type', $params['type']);
        }

        $q->where(function ($query) use ($userId) {
            return $query->where('user_id', $userId)->orWhereNull('user_id');
        });

        $q->where('active', 1);
        $q->orderBy('created_at', 'desc');

        return $q->paginate(perPage: $limit);
    }

    /**
     * Tipos cujo nome traduzido contém o termo buscado (sem diferenciar acentos/maiúsculas),
     * pois o nome exibido no cartão vem da tradução e não da coluna title.
     */
    private function typesMatching(string $term): array
    {
        $term = Str::lower(Str::ascii($term));

        return array_keys(array_filter(
            DocumentsEnum::getTexts(),
            fn (string $text) => str_contains(Str::lower(Str::ascii($text)), $term),
        ));
    }
}
