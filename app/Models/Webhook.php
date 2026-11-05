<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends Model
{
    use SoftDeletes;

    /**
     * Tópicos de notificação relacionados à assinatura do plano SaaS da PSI4
     * (conta Mercado Pago da própria PSI4).
     */
    public const PLAN_TOPICS = ['subscription_preapproval', 'subscription_authorized_payment'];

    /**
     * Tópico de notificação relacionado a uma cobrança feita por um terapeuta
     * a um paciente, na conta Mercado Pago conectada do terapeuta (Connect/split).
     */
    public const PAYMENT_TOPIC = 'payment';

    protected $table = 'webhook';

    protected $fillable = [
        'payload',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * `type` da notificação do Mercado Pago (ex.: `payment`, `subscription_preapproval`,
     * `subscription_authorized_payment`).
     */
    public function getType(): ?string
    {
        return $this->payload['type'] ?? null;
    }

    /**
     * `data.id` da notificação: identificador do recurso que deve ser buscado
     * na API do Mercado Pago (nunca vem o objeto completo no corpo do webhook).
     */
    public function getDataId(): ?string
    {
        $id = $this->payload['data']['id'] ?? null;

        return $id !== null ? (string) $id : null;
    }

    public function isPlanTopic(): bool
    {
        return in_array($this->getType(), self::PLAN_TOPICS, true);
    }

    public function isPaymentTopic(): bool
    {
        return $this->getType() === self::PAYMENT_TOPIC;
    }
}
