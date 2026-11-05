<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\NumberHelper;
use App\Jobs\NotifyNewRegistrationLeadJob;
use App\Models\RegistrationLeads;
use Illuminate\Support\Str;

class RegistrationLeadService
{
    public function __construct(private RegistrationLeads $leads)
    {
    }

    public function store(array $data): RegistrationLeads
    {
        $lead = $this->leads->upsertByWhatsapp(
            whatsapp: NumberHelper::onlyNumbers($data['whatsapp']),
            data: [
                'name' => Str::title(trim($data['name'])),
                'consent_at' => now(),
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
            ],
        );

        if ($lead->wasRecentlyCreated) {
            NotifyNewRegistrationLeadJob::dispatch($lead->id, $lead->name);
        }

        return $lead;
    }
}
