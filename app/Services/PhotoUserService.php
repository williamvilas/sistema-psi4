<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\FileHelper;
use App\Models\User;
use Illuminate\Http\UploadedFile;

readonly class PhotoUserService
{
    public function handle(User $user, ?UploadedFile $file = null): void
    {
        try {
            $photoOld = $user->photo;
            $this->changePhoto(user: $user, file: $file);

            if ($photoOld) {
                FileHelper::remove($photoOld);
            }
        } catch (\Throwable $t) {
            throw $t;
        }
    }

    private function changePhoto(User $user, ?UploadedFile $file = null): void
    {
        if ($file) {
            $file = FileHelper::uploadImage(file: $file, path: '/users', width: 150);
        }

        $user->photo = $file['path'] ?? null;

        if (! $user->save()) {
            throw new \DomainException(__('messages.failed_update'));
        }
    }
}
