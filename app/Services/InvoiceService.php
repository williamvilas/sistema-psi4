<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoicesStatusEnum;
use App\Jobs\RegisterLog;
use App\Models\Invoices;
use Illuminate\Support\Facades\Log;

readonly class InvoiceService
{
    public function __construct(private Invoices $invoices)
    {
    }

    public function store(array $payload): void
    {
        if (! $payload) {
            Log::warning('Nenhuma fatura para armazenar no banco de dados.');

            return;
        }

        try {
            $tax = $this->getTax($payload['amount']);
            $gatewayFee = $payload['gateway_fee'] ?? 0;
            $netAmount = ($payload['amount'] - $gatewayFee) - $tax;

            $this->invoices::create([
                'order_id' => $payload['order_id'],
                'status' => InvoicesStatusEnum::PENDING->value,
                'amount' => $payload['amount'],
                'gateway_fee' => $gatewayFee,
                'net_amount' => $netAmount,
                'tax' => $tax,
                'payload' => $payload['payload'],
            ]);

            Log::info('Fatura armzenada com sucesso.', ['order_id' => $payload['order_id']]);
        } catch (\Throwable $e) {
            $message = 'Falha em armazenar fatura. Message: '.$e->getMessage();
            Log::error($message, $payload);
            RegisterLog::dispatchSync([
                'data' => $payload,
                'message' => $message,
                'method' => 'InvoiceService->store',
            ]);
        }
    }

    public function cancelInvoice(string $orderId): void
    {
        $info = [
            'order_id' => $orderId,
        ];

        $cols = [
            'id',
            'status',
        ];

        $invoice = $this->invoices->newQuery()->select($cols)->where('order_id', $orderId)->first();

        if (! $invoice) {
            Log::warning('Fatura não encontrada. Não fazer nada', $info);

            return;
        }

        $status = match ($invoice->status) {
            InvoicesStatusEnum::PENDING->value => InvoicesStatusEnum::CANCELED->value,
            InvoicesStatusEnum::DONE->value => InvoicesStatusEnum::CANCEL->value,
        };

        $invoice->status = $status;

        if (! $invoice->save()) {
            $message = 'Falha em cancelar a fatura';
            Log::error($message, $info);

            RegisterLog::dispatchSync([
                'data' => $info,
                'message' => $message,
                'method' => 'InvoiceService->cancelInvoice',
            ]);
        }

        Log::info('Fatura cancelada com sucesso');
    }

    private function getTax(float $amount): float
    {
        $percentage = 15.5 / 100; // 15,5%

        $tax = $amount * $percentage;

        return round($tax, 2);
    }
}
