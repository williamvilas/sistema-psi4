<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AiUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AiUsageController extends Controller
{
    public function __construct(private readonly AiUsageService $service)
    {
    }

    /** Consumo de IA do mês e, com saldo, a autorização para chamar o microserviço. */
    public function index(): JsonResponse
    {
        return $this->response(fn () => $this->service->usage(Auth::user()));
    }
}
