<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\HealthReceiptBatches\DownloadRequest;
use App\Http\Requests\HealthReceiptBatches\ImportedRequest;
use App\Http\Requests\HealthReceiptBatches\StoreRequest;
use App\Http\Requests\HealthReceiptBatches\UndoRequest;
use App\Services\HealthReceiptBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HealthReceiptBatchesController extends Controller
{
    public function __construct(private readonly HealthReceiptBatchService $healthReceiptBatchService)
    {
    }

    public function paginate(): JsonResponse
    {
        return $this->response(function () {
            $paginate = $this->healthReceiptBatchService->paginate(userId: Auth::id(), limit: config('app.paginate'));

            return [
                'html' => view('health-receipts.batches-paginate', ['batches' => $paginate])->render(),
            ];
        });
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $result = $this->healthReceiptBatchService->generate(params: $request->toPayload(), user: Auth::user());

            return [
                'downloads' => array_map(fn ($batch) => [
                    'url' => route('health-receipts.batches.download', ['id' => encrypt($batch->id)]),
                    'count' => $batch->receipts_count,
                ], $result['batches']),
                'skipped' => $result['skipped'],
            ];
        });
    }

    public function download(DownloadRequest $request): StreamedResponse
    {
        try {
            $file = $this->healthReceiptBatchService->download(id: $request->getId() ?? 0, user: Auth::user());
        } catch (\DomainException) {
            abort(404);
        }

        return response()->streamDownload(
            fn () => print $file['content'],
            $file['filename'],
            ['Content-Type' => 'text/csv; charset=ISO-8859-1'],
        );
    }

    public function imported(ImportedRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->healthReceiptBatchService->markImported(id: $request->getId() ?? 0, userId: Auth::id());

            return $this->getMessageSuccess(message: __('health-receipts.imported_success'));
        });
    }

    public function undo(UndoRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->healthReceiptBatchService->undo(id: $request->getId() ?? 0, userId: Auth::id());

            return $this->getMessageSuccess(message: __('health-receipts.undone_success'));
        });
    }
}
