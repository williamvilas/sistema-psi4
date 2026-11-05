<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AgendaSettings;
use App\Models\BookingSettings;
use App\Models\PsychologistSettings;
use App\Models\User;
use App\Services\OnboardingService;
use App\Services\ProfileService;
use App\Services\PsychologistSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class OnboardingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private AgendaSettings $agendaSettings;

    private PsychologistSettings $psychologistSettings;

    private BookingSettings $bookingSettings;

    private User $userModel;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->user = User::factory()->create(['document' => null, 'whatsapp' => null]);
        Auth::login($this->user);

        $this->agendaSettings = Mockery::mock(AgendaSettings::class);
        $this->psychologistSettings = Mockery::mock(PsychologistSettings::class);
        $this->bookingSettings = Mockery::mock(BookingSettings::class);
        $this->userModel = Mockery::mock(User::class);
    }

    private function mockedService(): OnboardingService
    {
        return new OnboardingService(
            $this->agendaSettings,
            $this->psychologistSettings,
            $this->bookingSettings,
            $this->userModel,
            app(PsychologistSettingService::class),
            app(ProfileService::class),
        );
    }

    private function settingsPayload(): array
    {
        return [
            'clinic_name' => 'Dra. Maria',
            'cnpj' => null,
            'crp' => '06/123456',
            'cep' => null,
            'cidade' => null,
            'bairro' => null,
            'rua' => null,
            'numero' => null,
            'complemento' => null,
            'photo_data' => null,
            'remove_photo' => false,
        ];
    }

    private function mockAgenda(bool $exists): void
    {
        $this->agendaSettings->shouldReceive('findByUser')->andReturn($exists ? new AgendaSettings() : null);
    }

    private function mockCrp(?string $crp): void
    {
        $this->psychologistSettings->shouldReceive('findByUser')
            ->andReturn($crp === null ? null : new PsychologistSettings(['crp' => $crp]));
    }

    private function mockContact(?string $document, ?string $whatsapp): void
    {
        $this->userModel->shouldReceive('findById')
            ->with($this->user->id, ['id', 'document', 'whatsapp'])
            ->andReturn((new User())->forceFill(['document' => $document, 'whatsapp' => $whatsapp]));
    }

    public function testItStorePsychologistSavesSettingsAndContact(): void
    {
        // Arrange
        $service = app(OnboardingService::class);

        // Act
        $service->storePsychologist(
            user: $this->user,
            settings: $this->settingsPayload(),
            contact: ['document' => '52998224725', 'whatsapp' => '11988887777'],
        );

        // Assert
        $this->user->refresh();
        $this->assertSame('52998224725', $this->user->document);
        $this->assertSame('11988887777', $this->user->whatsapp);
        $this->assertSame('06123456', PsychologistSettings::query()->where('user_id', $this->user->id)->value('crp'));
    }

    public function testItStorePsychologistKeepsDocumentWhenAlreadyFilled(): void
    {
        // Arrange
        $this->user->forceFill(['document' => '52998224725'])->save();
        $service = app(OnboardingService::class);

        // Act
        $service->storePsychologist(
            user: $this->user,
            settings: $this->settingsPayload(),
            contact: ['document' => '11144477735', 'whatsapp' => '11988887777'],
        );

        // Assert
        $this->user->refresh();
        $this->assertSame('52998224725', $this->user->document);
        $this->assertSame('11988887777', $this->user->whatsapp);
    }

    public function testItStorePsychologistRollsBackSettingsWhenContactFails(): void
    {
        // Arrange
        $profileService = Mockery::mock(ProfileService::class);
        $profileService->shouldReceive('update')->once()->andThrow(new \DomainException(__('messages.failed_update')));

        $service = new OnboardingService(
            app(AgendaSettings::class),
            app(PsychologistSettings::class),
            app(BookingSettings::class),
            app(User::class),
            app(PsychologistSettingService::class),
            $profileService,
        );

        // Act
        try {
            $service->storePsychologist(
                user: $this->user,
                settings: $this->settingsPayload(),
                contact: ['document' => '52998224725', 'whatsapp' => '11988887777'],
            );
            $this->fail('Era esperada uma DomainException.');
        } catch (\DomainException) {
            // Assert
            $this->assertFalse(PsychologistSettings::query()->where('user_id', $this->user->id)->exists());
        }
    }

    public function testItCurrentStepReturnsAgendaWhenAgendaMissing(): void
    {
        // Arrange
        $this->mockAgenda(false);

        // Act
        $step = $this->mockedService()->currentStep($this->user->id);

        // Assert
        $this->assertSame(OnboardingService::STEP_AGENDA, $step);
    }

    public function testItCurrentStepReturnsPsychologistWhenCrpMissing(): void
    {
        // Arrange
        $this->mockAgenda(true);
        $this->mockCrp(null);
        $this->userModel->shouldNotReceive('findById');

        // Act
        $step = $this->mockedService()->currentStep($this->user->id);

        // Assert
        $this->assertSame(OnboardingService::STEP_PSYCHOLOGIST, $step);
    }

    public function testItCurrentStepReturnsPsychologistWhenDocumentMissing(): void
    {
        // Arrange
        $this->mockAgenda(true);
        $this->mockCrp('06123456');
        $this->mockContact(null, '11988887777');

        // Act
        $step = $this->mockedService()->currentStep($this->user->id);

        // Assert
        $this->assertSame(OnboardingService::STEP_PSYCHOLOGIST, $step);
    }

    public function testItCurrentStepReturnsPsychologistWhenWhatsappMissing(): void
    {
        // Arrange
        $this->mockAgenda(true);
        $this->mockCrp('06123456');
        $this->mockContact('52998224725', null);

        // Act
        $step = $this->mockedService()->currentStep($this->user->id);

        // Assert
        $this->assertSame(OnboardingService::STEP_PSYCHOLOGIST, $step);
    }

    public function testItCurrentStepReturnsBookingWhenProfileComplete(): void
    {
        // Arrange
        $this->mockAgenda(true);
        $this->mockCrp('06123456');
        $this->mockContact('52998224725', '11988887777');

        // Act
        $step = $this->mockedService()->currentStep($this->user->id);

        // Assert
        $this->assertSame(OnboardingService::STEP_BOOKING, $step);
    }

    public function testItCompleteThrowExceptionWhenContactMissing(): void
    {
        // Arrange
        $this->mockAgenda(true);
        $this->mockCrp('06123456');
        $this->mockContact(null, null);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('onboarding.incomplete'));

        // Act
        $this->mockedService()->complete($this->user);
    }

    public function testItCompleteMarksOnboardingCompleted(): void
    {
        // Arrange
        $this->mockAgenda(true);
        $this->mockCrp('06123456');
        $this->mockContact('52998224725', '11988887777');
        $this->bookingSettings->shouldReceive('findByUser')->andReturn(new BookingSettings());

        // Act
        $this->mockedService()->complete($this->user);

        // Assert
        $this->assertTrue($this->user->refresh()->hasCompletedOnboarding());
    }
}
