<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Zipcode\ShowRequest;
use App\Services\ZipcodeService;
use Illuminate\Http\JsonResponse;

class ZipcodeController extends Controller
{
    public function __construct(private readonly ZipcodeService $zipcodeService)
    {
    }

    public function show(ShowRequest $request): JsonResponse
    {
        return $this->response(fn () => $this->zipcodeService->findByZipcode($request->getZipcode()));
    }
}
