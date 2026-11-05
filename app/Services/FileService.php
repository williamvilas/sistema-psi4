<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

readonly class FileService
{
    public function __construct(private Storage $storage)
    {
    }

    public function exists(string $path): bool
    {
        return $this->storage::disk('s3')->exists($path);
    }

    public function put(string $path, string $contents): bool
    {
        return $this->storage::disk('s3')->put($path, $contents);
    }

    public function temporaryUrlToUpload(string $path, string $contentType, int $minutes = 30): array
    {
        if ($this->storage::disk('s3')->exists($path)) {
            throw ValidationException::withMessages(['path' => 'Esse arquivo já existe.']);
        }

        return $this->storage::disk('s3')->temporaryUploadUrl(
            path: $path,
            expiration: now()->addMinutes($minutes),
            options: [
                'ContentType' => $contentType,
            ]
        );
    }
}
