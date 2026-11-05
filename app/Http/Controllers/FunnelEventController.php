<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FunnelEventController\StoreRequest;
use App\Services\FunnelEventService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class FunnelEventController extends Controller
{
    public function store(StoreRequest $request, FunnelEventService $service): Response
    {
        $service->store(
            data: $request->validated(),
            userId: Auth::id(),
            userAgent: $request->userAgent(),
        );

        return response()->noContent();
    }
}
