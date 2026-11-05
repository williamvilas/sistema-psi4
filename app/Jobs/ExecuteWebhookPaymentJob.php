<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PlanCycleEnum;
use App\Enums\WebhookStatusEnum;
use App\Helpers\PlanHelper;
use App\Models\PlanSubscription;
use App\Models\Webhook;
use App\Services\InvoiceService;
use App\Services\ProcessMercadoPagoChargeWebhookService;
use App\Services\ProcessMercadoPagoPlanWebhookService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecuteWebhookPaymentJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    // Webhook removido (soft delete) antes do processamento: descarta em vez de falhar
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Webhook $webhook)
    {
    }

    public function uniqueId(): string
    {
        return 'webhook_'.$this->webhook->id;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('webhook_'.$this->webhook->id))
                ->expireAfter(300), // segurança contra lock preso
        ];
    }

    public function backoff(): array
    {
        return [10, 30, 90, 300, 900];
    }

    public function handle(
        ProcessMercadoPagoPlanWebhookService $processPlanWebhookService,
        ProcessMercadoPagoChargeWebhookService $processChargeWebhookService,
        InvoiceService $invoiceService,
    ): void {
        // Worker é de longa duração: o contexto precisa ser limpo ao final do job
        Log::withContext(['webhook_id' => $this->webhook->id]);

        try {
            $this->process($processPlanWebhookService, $processChargeWebhookService, $invoiceService);
        } finally {
            Log::withoutContext(['webhook_id']);
        }
    }

    private function process(
        ProcessMercadoPagoPlanWebhookService $processPlanWebhookService,
        ProcessMercadoPagoChargeWebhookService $processChargeWebhookService,
        InvoiceService $invoiceService,
    ): void {
        Log::info('Inicio do processamento do webhook Mercado Pago');

        if ($this->webhook->status === WebhookStatusEnum::DONE->value) {
            Log::warning('Webhook já processado');

            return;
        }

        $this->webhook->update(['status' => WebhookStatusEnum::PROCESSING->value]);

        try {
            DB::beginTransaction();

            if ($this->webhook->isPlanTopic()) {
                Log::info('Processando notificação da assinatura do plano SaaS');
                $result = $processPlanWebhookService->handle($this->webhook);

                if ($result) {
                    $payload = $this->buildInvoicePayload($result['resource'], $result['subscription']);

                    if ($payload) {
                        Log::info('Preparando para armazenar fatura da assinatura');
                        $invoiceService->store($payload);
                    }
                }
            } elseif ($this->webhook->isPaymentTopic()) {
                // O tópico `payment` é compartilhado: pagamento do plano anual da PSI4 ou
                // cobrança de paciente (Connect). O plano anual se identifica pelo external_reference.
                $planPayment = $processPlanWebhookService->handlePayment($this->webhook);

                if ($planPayment === null) {
                    Log::info('Processando notificação de cobrança de paciente (Mercado Pago Connect)');
                    $processChargeWebhookService->handle($this->webhook);
                } elseif ($planPayment['approved']) {
                    Log::info('Processando pagamento do plano anual da assinatura');
                    $payload = $this->buildInvoicePayload($planPayment['resource'], $planPayment['subscription']);

                    if ($payload) {
                        $invoiceService->store($payload);
                    }
                }
            } else {
                Log::warning("Tópico de webhook não tratado: {$this->webhook->getType()}");
            }

            $this->webhook->update(['status' => WebhookStatusEnum::DONE->value]);
            DB::commit();

            Log::info('Webhook processado com sucesso');
        } catch (\Throwable $e) {
            DB::rollBack();

            $message = 'Falha em processar webhook. Message: '.$e->getMessage();
            Log::error($message);

            $this->webhook->update(['status' => WebhookStatusEnum::ERROR->value]);

            // Alerta (RegisterLog) é disparado centralizadamente pelo listener
            // de JobFailed em AppServiceProvider quando o job falhar de vez.
            throw $e;
        }
    }

    /**
     * Monta a fatura fiscal da própria receita da PSI4 (assinatura do plano
     * SaaS) a partir do recurso `authorized_payment` do Mercado Pago.
     *
     * ⚠️ Limitações conhecidas em relação ao payload antigo do Kiwify:
     * - O Mercado Pago não retorna o endereço completo do pagador
     *   (logradouro/número/bairro/cidade/estado/CEP): o campo `endereco` fica
     *   `null`. Se a emissão fiscal exigir esse dado, será necessário coletar
     *   o endereço do usuário em outro momento (ex.: cadastro/checkout) — não
     *   deve ser inventado aqui.
     * - A taxa cobrada pelo Mercado Pago (`gateway_fee`) depende do campo
     *   `fee_details` do recurso, cujo formato deve ser confirmado com uma
     *   notificação real antes de ir para produção; na ausência do campo o
     *   valor é registrado como 0 e um aviso é logado.
     */
    private function buildInvoicePayload(array $resource, PlanSubscription $subscription): array
    {
        $authorizedPaymentId = (string) ($resource['id'] ?? '');

        if ($authorizedPaymentId === '' || ! $subscription->user) {
            Log::warning('Assinatura/usuário não encontrado para fatura do authorized_payment');

            return [];
        }

        try {
            $grossAmount = round((float) ($resource['transaction_amount'] ?? 0), 2);
            $gatewayFee = round((float) ($resource['fee_details'][0]['amount'] ?? 0), 2);

            if ($gatewayFee === 0.0) {
                Log::warning("gateway_fee não encontrado no authorized_payment {$authorizedPaymentId}; usando 0.");
            }

            $user = $subscription->user;

            return [
                'order_id' => $authorizedPaymentId,
                'amount' => $grossAmount,
                'gateway_fee' => $gatewayFee,
                'payload' => [
                    'tomador' => [
                        'nome' => $user->name,
                        'cpf_cnpj' => $user->document,
                        'email' => $user->email,
                        'telefone' => $user->whatsapp,
                        'endereco' => null,
                    ],

                    'servico' => [
                        'descricao' => $subscription->cycle === PlanCycleEnum::ANNUAL
                            ? 'Licenciamento de uso do software PSI4 referente ao plano anual, de '.now()->format('d/m/Y').' a '.now()->addDays(PlanHelper::annualDays($subscription->plan->value))->format('d/m/Y').'.'
                            : 'Renovação de licenciamento de uso do software PSI4 referente ao período '.now()->format('m/Y').'.',
                        'valor_bruto' => $grossAmount,
                        'valor_gateway_fee' => $gatewayFee,
                        'data_emissao' => now()->format('Y-m-d'),
                        'codigo_servico' => '6202300',
                        'aliquota_iss' => null,
                    ],

                    'referencias' => [
                        'order_id' => $authorizedPaymentId,
                        'preapproval_id' => $subscription->preapproval_id,
                        'plano' => $subscription->plan->value,
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            RegisterLog::dispatchSync([
                'data' => ['id' => $this->webhook->id],
                'message' => $e->getMessage(),
                'method' => 'ExecuteWebhookPaymentJob->buildInvoicePayload',
            ]);

            return [];
        }
    }
}
