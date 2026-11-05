<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\BookingSettings;
use App\Models\PsychologistServices;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class BookingSettingService
{
    public function __construct(
        private BookingSettings $bookingSettings,
        private PsychologistServices $services,
        private User $users,
    ) {
    }

    public function services(int $userId): Collection
    {
        return $this->services->listByUser($userId);
    }

    public function details(int $userId): ?BookingSettings
    {
        $key = $this->cacheKey($userId);
        $settings = CacheHelper::get(key: $key);

        if ($settings) {
            return $settings;
        }

        $settings = $this->bookingSettings->findByUser($userId);

        if ($settings) {
            CacheHelper::put(key: $key, value: $settings, ttl: now()->addMinutes(5));
        }

        return $settings;
    }

    public function store(array $data, int $userId): BookingSettings
    {
        $settings = DB::transaction(function () use ($data, $userId) {
            $this->users->newQuery()->where('id', $userId)->update(['slug' => $data['slug']]);
            $this->syncServices($userId, $data['services']);

            return $this->bookingSettings->saveByUser($userId, [
                'public_booking_enabled' => $data['public_booking_enabled'],
            ]);
        });

        CacheHelper::forget($this->cacheKey($userId));

        return $settings;
    }

    /**
     * Estado do link público exibido na Agenda: só há link quando a página está ativa.
     *
     * @return array{enabled: bool, url: ?string, custom_slug: bool}
     */
    public function publicLink(User $user): array
    {
        $slug = (string) $user->slug;
        $enabled = $slug !== '' && (bool) $this->details($user->id)?->public_booking_enabled;

        return [
            'enabled' => $enabled,
            'url' => $enabled ? route('public-booking.show', ['slug' => $slug]) : null,
            'custom_slug' => $slug !== '' && ! Str::isUuid($slug),
        ];
    }

    public function isSlugAvailable(string $slug, int $userId): bool
    {
        return ! $this->users->slugExists($slug, $userId);
    }

    /**
     * Sugere um endereço a partir do nome quando o usuário ainda está com o slug padrão (UUID).
     */
    public function suggestSlug(User $user): string
    {
        if (! Str::isUuid((string) $user->slug)) {
            return (string) $user->slug;
        }

        $base = Str::limit(Str::slug($user->name), 50, '');

        if (strlen($base) < 3) {
            return (string) $user->slug;
        }

        $slug = $base;
        $suffix = 2;

        while ($this->users->slugExists($slug, $user->id)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function toggle(bool $enabled, int $userId): bool
    {
        if ($enabled && ! $this->users->newQuery()->where('id', $userId)->value('slug')) {
            throw new \DomainException(__('public-booking.slug_required'));
        }

        $settings = $this->bookingSettings->saveByUser($userId, [
            'public_booking_enabled' => $enabled,
        ]);

        CacheHelper::forget($this->cacheKey($userId));

        return $settings->public_booking_enabled;
    }

    private function syncServices(int $userId, array $services): void
    {
        $keepIds = [];

        foreach ($services as $item) {
            $payload = ['name' => $item['name'], 'price' => $item['price']];
            $id = (int) ($item['id'] ?? 0);

            if ($id > 0 && $this->services->findByUser($id, $userId, ['id'])) {
                $this->services->updateByUser($id, $userId, $payload);
                $keepIds[] = $id;

                continue;
            }

            $keepIds[] = $this->services->newQuery()->create($payload + ['user_id' => $userId])->id;
        }

        $this->services->deleteExcept($userId, $keepIds);
    }

    private function cacheKey(int $userId): string
    {
        return "booking_settings_user_{$userId}";
    }
}
