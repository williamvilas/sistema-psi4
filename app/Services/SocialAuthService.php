<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\FileHelper;
use App\Helpers\UserHelper;
use App\Jobs\GenerateDefaultSettingsJob;
use App\Jobs\RegisterLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SocialAuthService
{
    private const PROVIDERS = [
        'google' => ['driver' => 'google', 'column' => 'google_id', 'label' => 'Google'],
        'linkedin' => ['driver' => 'linkedin-openid', 'column' => 'linkedin_id', 'label' => 'LinkedIn'],
    ];

    public function __construct(
        private User $user,
        private RegistrationInvitationService $invitations,
    ) {
    }

    public function driver(string $provider): string
    {
        return $this->providerConfig($provider)['driver'];
    }

    public function label(string $provider): string
    {
        return $this->providerConfig($provider)['label'];
    }

    public function resolveUser(string $provider, SocialiteUser $socialUser): User
    {
        $config = $this->providerConfig($provider);
        $email = Str::lower((string) $socialUser->getEmail());
        $raw = (array) ($socialUser->user ?? []);

        if (empty($email) || ! filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new \DomainException(__('auth.social_email_unverified', ['provider' => $config['label']]));
        }

        $user = DB::transaction(function () use ($config, $socialUser, $email) {
            $user = $this->user->findBySocialId($config['column'], (string) $socialUser->getId())
                ?? $this->user->findByEmail($email);

            if ($user) {
                return $this->linkAccount($user, $config['column'], $socialUser);
            }

            // Conta nova só com convite válido (REGISTRATION_INVITE_ONLY); consumido na mesma transação.
            $created = $this->createUser($config['column'], $socialUser, $email);
            $this->invitations->consumeForUser($created);

            return $created;
        });

        if ($user->wasRecentlyCreated) {
            event(new Registered($user));
            GenerateDefaultSettingsJob::dispatchSync(userId: $user->id);
        }

        if (empty($user->photo)) {
            $this->savePhoto($user, $socialUser->getAvatar(), $config['label']);
        }

        return $user;
    }

    private function providerConfig(string $provider): array
    {
        return self::PROVIDERS[$provider] ?? throw new \DomainException(__('auth.social_failed', ['provider' => $provider]));
    }

    private function linkAccount(User $user, string $column, SocialiteUser $socialUser): User
    {
        $user->{$column} = (string) $socialUser->getId();

        if (empty($user->email_verified_at)) {
            $user->email_verified_at = now();
        }

        $user->save();

        return $user;
    }

    private function createUser(string $column, SocialiteUser $socialUser, string $email): User
    {
        $user = new User([
            $column => (string) $socialUser->getId(),
            'name' => $socialUser->getName() ?: Str::before($email, '@'),
            'email' => $email,
            'password' => Str::random(40),
            'code' => UserHelper::generateCode(),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Salva o avatar do provedor social no mesmo local da foto de perfil (users.photo).
     * Falha ao baixar a imagem não impede o login.
     */
    private function savePhoto(User $user, ?string $avatarUrl, string $label): void
    {
        if (empty($avatarUrl)) {
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'social_avatar_');

        try {
            $response = Http::timeout(10)->get(preg_replace('/=s\d+-c$/', '=s300-c', $avatarUrl));

            if (! $response->successful()) {
                return;
            }

            File::put($tmp, $response->body());
            $file = new UploadedFile($tmp, 'social-avatar.jpg', null, null, true);
            $uploaded = FileHelper::uploadImage(file: $file, path: '/users', width: 150);

            $this->user->updatePhotoById($user->id, $uploaded['path']);
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => 'Falha ao salvar foto do '.$label.': '.$t->getMessage(),
                'route' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
        } finally {
            File::delete($tmp);
        }
    }
}
