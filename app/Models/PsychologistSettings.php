<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\FileHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologistSettings extends Model
{
    protected $table = 'psychologist_settings';

    protected $fillable = [
        'user_id',
        'clinic_name',
        'cnpj',
        'crp',
        'cep',
        'cidade',
        'bairro',
        'rua',
        'numero',
        'complemento',
        'photo_path',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoUrl(int $minutes = 60): ?string
    {
        return $this->photo_path ? FileHelper::getLinkTemp(path: $this->photo_path, minutes: $minutes) : null;
    }

    /**
     * Endereço necessário para o atendimento presencial: rua, número e cidade.
     */
    public function hasAddress(): bool
    {
        return filled($this->rua) && filled($this->numero) && filled($this->cidade);
    }

    /**
     * Endereço em linhas: "Rua, número — complemento" e "Bairro, cidade".
     *
     * @return string[]
     */
    public function addressLines(): array
    {
        return collect([
            trim(($this->rua ?? '').($this->numero ? ', '.$this->numero : '').($this->complemento ? ' — '.$this->complemento : '')),
            implode(', ', array_filter([trim((string) $this->bairro), trim((string) $this->cidade)])),
        ])->filter()->values()->all();
    }

    public function fullAddress(): ?string
    {
        return implode(' · ', $this->addressLines()) ?: null;
    }

    /**
     * Rota no Google Maps da localização do paciente até o consultório. O complemento
     * (sala, bloco) fica de fora porque atrapalha a busca do endereço.
     */
    public function mapsUrl(): ?string
    {
        if (! $this->hasAddress()) {
            return null;
        }

        $destination = implode(', ', array_filter([
            trim((string) $this->rua),
            trim((string) $this->numero),
            trim((string) $this->bairro),
            trim((string) $this->cidade),
            trim((string) $this->cep),
            'Brasil',
        ]));

        return 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($destination);
    }

    public function findByUser(int $userId, array $cols = ['*']): ?self
    {
        return self::query()->select($cols)->where('user_id', $userId)->first();
    }

    public function saveByUser(int $userId, array $data): self
    {
        return self::query()->updateOrCreate(['user_id' => $userId], $data);
    }
}
