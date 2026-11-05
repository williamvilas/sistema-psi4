<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PublicBooking\BookRequest;
use App\Http\Requests\PublicBooking\ConfirmedRequest;
use App\Services\PublicBookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PublicBookingController extends Controller
{
    public function __construct(private readonly PublicBookingService $publicBookingService)
    {
    }

    public function show(string $slug): View
    {
        $context = $this->publicBookingService->findActive($slug);

        abort_if(empty($context), 404);

        return view('public-booking.index', [
            'slug' => $slug,
            'profile' => $this->publicBookingService->profile($context),
        ]);
    }

    public function availability(string $slug): JsonResponse
    {
        return $this->response(function () use ($slug) {
            $context = $this->publicBookingService->findActive($slug);

            if (empty($context)) {
                throw new \DomainException(__('public-booking.not_found'));
            }

            return [
                'days' => $this->publicBookingService->availability($context),
            ];
        });
    }

    public function book(BookRequest $request, string $slug): JsonResponse
    {
        return $this->response(function () use ($request, $slug) {
            $summary = $this->publicBookingService->book(slug: $slug, data: $request->toBook());

            return [
                'link' => route('public-booking.confirmed', [
                    'slug' => $slug,
                    'd' => encrypt(json_encode($summary)),
                ]),
            ];
        });
    }

    public function confirmed(ConfirmedRequest $request, string $slug): View|RedirectResponse
    {
        $context = $this->publicBookingService->findActive($slug);

        abort_if(empty($context), 404);

        $summary = $request->getSummary();

        if (! $summary) {
            return redirect()->route('public-booking.show', ['slug' => $slug]);
        }

        return view('public-booking.confirmed', [
            'slug' => $slug,
            'profile' => $this->publicBookingService->profile($context),
            'summary' => $summary,
        ]);
    }
}
