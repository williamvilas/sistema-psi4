<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgendaSettings;
use App\Models\BookingSettings;
use App\Models\PsychologistSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class OnboardingService
{
    public const STEP_AGENDA = 1;
    public const STEP_PSYCHOLOGIST = 2;
    public const STEP_BOOKING = 3;

    public function __construct(
        private AgendaSettings $agendaSettings,
        private PsychologistSettings $psychologistSettings,
        private BookingSettings $bookingSettings,
        private User $user,
        private PsychologistSettingService $psychologistSettingService,
        private ProfileService $profileService,
    ) {
    }

    /**
     * Primeira etapa ainda pendente: agenda, dados do psicólogo e agendamento online.
     */
    public function currentStep(int $userId): int
    {
        if (! $this->hasAgenda($userId)) {
            return self::STEP_AGENDA;
        }

        if (! $this->hasPsychologist($userId)) {
            return self::STEP_PSYCHOLOGIST;
        }

        return self::STEP_BOOKING;
    }

    /**
     * Salva os dados profissionais e o CPF/WhatsApp da conta juntos; o CPF já informado não é sobrescrito.
     */
    public function storePsychologist(User $user, array $settings, array $contact): void
    {
        DB::transaction(function () use ($user, $settings, $contact) {
            $this->psychologistSettingService->store(data: $settings, userId: $user->id);
            $this->profileService->update(user: $user, payload: $contact);
        });
    }

    public function complete(User $user): void
    {
        if (! $this->hasAgenda($user->id) || ! $this->hasPsychologist($user->id) || ! $this->hasBooking($user->id)) {
            throw new \DomainException(__('onboarding.incomplete'));
        }

        $user->markOnboardingCompleted();
    }

    private function hasAgenda(int $userId): bool
    {
        return $this->agendaSettings->findByUser($userId, ['id']) !== null;
    }

    private function hasPsychologist(int $userId): bool
    {
        if (empty($this->psychologistSettings->findByUser($userId, ['id', 'crp'])?->crp)) {
            return false;
        }

        $user = $this->user->findById($userId, ['id', 'document', 'whatsapp']);

        return ! empty($user?->document) && ! empty($user?->whatsapp);
    }

    private function hasBooking(int $userId): bool
    {
        return $this->bookingSettings->findByUser($userId, ['id']) !== null;
    }
}
