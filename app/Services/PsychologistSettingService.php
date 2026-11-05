<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Helpers\FileHelper;
use App\Models\PsychologistSettings;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

readonly class PsychologistSettingService
{
    public function __construct(
        private PsychologistSettings $psychologistSettings,
        private User $user,
    ) {
    }

    public function details(int $userId): ?PsychologistSettings
    {
        $key = $this->cacheKey($userId);
        $settings = CacheHelper::get(key: $key);

        if ($settings) {
            return $settings;
        }

        $settings = $this->psychologistSettings->findByUser($userId);

        if ($settings) {
            CacheHelper::put(key: $key, value: $settings, ttl: now()->addMinutes(5));
        }

        return $settings;
    }

    public function store(array $data, int $userId): PsychologistSettings
    {
        $oldPhotoPath = $this->psychologistSettings->findByUser($userId, ['id', 'photo_path'])?->photo_path;
        $oldUserPhoto = Auth::user()?->photo;

        $photoPath = $oldPhotoPath;
        $userPhotoPath = null;
        $photoChanged = false;

        if (! empty($data['photo_data'])) {
            $photoPath = $this->storePhoto($data['photo_data'], $userId);
            $photoChanged = true;
        } elseif (! empty($data['remove_photo']) && $oldPhotoPath) {
            $photoPath = null;
            $photoChanged = true;
        }

        try {
            if ($photoChanged && $photoPath) {
                $userPhotoPath = $this->copyToUserPhoto($photoPath);
            }

            $settings = DB::transaction(function () use ($data, $userId, $photoPath, $userPhotoPath, $photoChanged) {
                $settings = $this->psychologistSettings->saveByUser($userId, [
                    'clinic_name' => $data['clinic_name'],
                    'cnpj' => $this->onlyDigits($data['cnpj']),
                    'crp' => $this->onlyDigits($data['crp']),
                    'cep' => $this->onlyDigits($data['cep']),
                    'cidade' => $data['cidade'],
                    'bairro' => $data['bairro'],
                    'rua' => $data['rua'],
                    'numero' => $data['numero'],
                    'complemento' => $data['complemento'],
                    'photo_path' => $photoPath,
                ]);

                if ($photoChanged) {
                    $this->user->updatePhotoById($userId, $userPhotoPath);
                }

                return $settings;
            });
        } catch (\Throwable $exception) {
            if ($photoPath && $photoPath !== $oldPhotoPath) {
                Storage::delete($photoPath);
            }

            if ($userPhotoPath) {
                Storage::delete($userPhotoPath);
            }

            throw $exception;
        }

        CacheHelper::forget($this->cacheKey($userId));

        if ($photoChanged) {
            CacheHelper::forget('photo_user_'.$userId);

            if ($oldPhotoPath) {
                FileHelper::remove($oldPhotoPath);
            }

            if ($oldUserPhoto && $oldUserPhoto !== $oldPhotoPath) {
                FileHelper::remove($oldUserPhoto);
            }
        }

        return $settings;
    }

    private function copyToUserPhoto(string $photoPath): string
    {
        $userPhotoPath = 'users/'.Str::uuid().'.'.pathinfo($photoPath, PATHINFO_EXTENSION);

        if (! Storage::copy($photoPath, $userPhotoPath)) {
            throw new \DomainException(__('messages.failed_update'));
        }

        return $userPhotoPath;
    }

    private function onlyDigits(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    private function storePhoto(string $photoData, int $userId): string
    {
        if (! preg_match('/^data:image\/(jpeg|png|webp);base64,(.+)$/', $photoData, $matches)) {
            throw new \DomainException(__('psychologist.invalid_photo'));
        }

        $contents = base64_decode($matches[2], true);

        if ($contents === false || strlen($contents) > 5 * 1024 * 1024) {
            throw new \DomainException(__('psychologist.invalid_photo'));
        }

        $extension = match (getimagesizefromstring($contents)['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \DomainException(__('psychologist.invalid_photo')),
        };

        $path = "psychologists/{$userId}/".Str::uuid().".{$extension}";

        if (! Storage::put($path, $contents)) {
            throw new \DomainException(__('messages.failed_update'));
        }

        return $path;
    }

    private function cacheKey(int $userId): string
    {
        return "psychologist_settings_user_{$userId}";
    }
}
