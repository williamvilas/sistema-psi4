<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class FileHelper
{
    public static function upload(UploadedFile $file, string $path): array
    {
        $filename = Str::uuid()->toString().'-'.uniqid().'.'.$file->getClientOriginalExtension();
        $path = Storage::putFileAs($path, $file, $filename);

        if (! $path) {
            throw new \Exception('upload_failed');
        }

        return [
            'path' => $path,
            'filename' => $filename,
            'size' => $file->getSize(),
        ];
    }

    public static function uploadImage(UploadedFile $file, string $path, int $width = 800, string $disk = 's3'): array
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getContent());

        $image->scale(width: $width);
        $extension = strtolower($file->getClientOriginalExtension());

        $filename = Str::uuid()->toString().'-'.uniqid().'.'.$extension;
        $fullPath = rtrim($path, '/').'/'.$filename;

        $encoded = $image->encode(
            new WebpEncoder(quality: 80)
        );

        Storage::disk($disk)->put(
            $fullPath,
            (string) $encoded
        );

        return [
            'path' => $fullPath,
            'filename' => $filename,
            'size' => Storage::disk($disk)->size($fullPath),
        ];
    }

    public static function remove(string $path): bool
    {
        if (Storage::exists($path)) {
            return Storage::delete($path);
        }

        return false;
    }

    public static function removeDirectory(string $path): bool
    {
        return Storage::deleteDirectory($path);
    }

    public static function getLinkTemp(string $path, int $minutes): string
    {
        return Storage::temporaryUrl(path: $path, expiration: now()->addMinutes($minutes));
    }

    public static function move(string $from, string $to): string
    {
        $r = Storage::move($from, $to);

        if (! $r) {
            throw new \Exception('Falha em mover arquivo de lugar');
        }

        return $to;
    }
}
