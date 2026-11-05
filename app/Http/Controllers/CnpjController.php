<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Cnpj\ShowRequest;
use App\Services\CnpjService;
use Illuminate\Http\JsonResponse;

class CnpjController extends Controller
{
    public function __construct(private readonly CnpjService $cnpjService)
    {
    }

    public function show(ShowRequest $request): JsonResponse
    {
        return $this->response(fn () => $this->cnpjService->findByCnpj($request->getCnpj()));
    }
}
