<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\UserHelper;
use App\Http\Requests\ScaleLinks\PaginateRequest;
use App\Http\Requests\ScaleLinks\RevokeRequest;
use App\Http\Requests\ScaleLinks\StoreRequest;
use App\Services\ScaleLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ScaleLinksController extends Controller
{
    private const LIMIT = 5;

    public function __construct(private readonly ScaleLinkService $scaleLinkService)
    {
    }

    public function paginate(PaginateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $links = $this->scaleLinkService->paginateByPatient(
                patientId: $request->getPatientId(),
                userId: Auth::id(),
                limit: self::LIMIT,
            )->withQueryString();

            return [
                'html' => view('patients.partials.scale-links-paginate', [
                    'links' => $links,
                    'timezone' => UserHelper::getTimezone(),
                ])->render(),
            ];
        });
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $link = $this->scaleLinkService->store(
                patientId: $request->getPatientId(),
                scale: $request->getScale(),
                userId: Auth::id(),
                timezone: UserHelper::getTimezone(),
            );

            return [
                'url' => route('public-scale.show', ['token' => $link->token]),
                'scale' => $link->scale->label(),
                'expires_at' => formatDate($link->expires_at->toDateTimeString(), 'd/m/Y', UserHelper::getTimezone()),
                'created' => $link->wasRecentlyCreated,
            ];
        });
    }

    public function revoke(RevokeRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->scaleLinkService->revoke(id: $request->getId(), userId: Auth::id());

            return $this->getMessageSuccess(message: __('scales.link_revoked'));
        });
    }
}
