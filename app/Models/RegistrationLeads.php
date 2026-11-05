<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class RegistrationLeads extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_INVITED = 'invited';
    public const STATUS_REGISTERED = 'registered';

    protected $table = 'registration_leads';

    protected $fillable = [
        'name',
        'whatsapp',
        'status',
        'consent_at',
        'utm_source',
        'utm_medium',
        'utm_campaign',
    ];

    protected $casts = [
        'consent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * O mesmo WhatsApp não gera outro registro: só atualiza o nome informado por último.
     */
    public function upsertByWhatsapp(string $whatsapp, array $data): self
    {
        $lead = self::query()->firstOrNew(['whatsapp' => $whatsapp]);
        $lead->fill($data);
        $lead->save();

        return $lead;
    }

    public function findById(int $id): ?self
    {
        return self::query()->find($id);
    }

    public function getPending(): Collection
    {
        return self::query()
            ->whereIn('status', [self::STATUS_NEW, self::STATUS_CONTACTED])
            ->orderBy('created_at')
            ->get();
    }

    public function updateStatusById(int $id, string $status): void
    {
        self::query()->whereKey($id)->update(['status' => $status]);
    }
}
