<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Files\TemporaryUrlRequest;
use App\Services\FileService;

class FilesController extends Controller
{
    public function __construct(private readonly FileService $service)
    {
    }

    public function temporaryUrlToUpload(TemporaryUrlRequest $request)
    {
        return $this->response(function () use ($request) {
            $response = $this->service->temporaryUrlToUpload(
                path: $request->input('path'),
                contentType: $request->input('content_type')
            );

            return [
                'link' => $response['url'],
            ];
        });
    }
}
