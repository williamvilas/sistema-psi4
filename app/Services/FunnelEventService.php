<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FunnelEvent;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

readonly class FunnelEventService
{
    public function __construct(
        private FunnelEvent $funnelEvent,
        private CrawlerDetect $crawlerDetect,
    ) {
    }

    /**
     * Registra a tag do funil. Bots são descartados (user agent não é armazenado)
     * e o mesmo visitante só registra uma vez cada tag.
     */
    public function store(array $data, ?int $userId = null, ?string $userAgent = null): bool
    {
        if ($this->crawlerDetect->isCrawler($userAgent)) {
            return false;
        }

        return $this->funnelEvent->storeIgnoringDuplicate([
            'visitor_id' => $data['visitor_id'],
            'tag' => $data['tag'],
            'user_id' => $userId,
            'path' => $data['path'],
            'referrer' => $data['referrer'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
        ]);
    }
}
