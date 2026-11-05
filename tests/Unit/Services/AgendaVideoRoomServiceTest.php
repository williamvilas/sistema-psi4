<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AgendaStatusEnum;
use App\Enums\VideoRoomRoleEnum;
use App\Enums\VideoRoomStatusEnum;
use App\Models\Agenda;
use App\Models\Patients;
use App\Models\User;
use App\Models\VideoRoom;
use App\Services\AgendaVideoRoomService;
use App\Services\CpfHasher;
use App\Services\PhoneHasher;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class AgendaVideoRoomServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AgendaVideoRoomService $service;

    private CpfHasher $cpfHasher;

    private PhoneHasher $phoneHasher;

    private User $user;

    private Patients $patient;

    protected function setUp(): void
    {
        parent::setUp();

        // Observers disparam jobs reais de sincronização/notificação
        Bus::fake();

        $this->service = app(AgendaVideoRoomService::class);
        $this->cpfHasher = new CpfHasher;
        $this->phoneHasher = new PhoneHasher;
        $this->user = User::factory()->create(['name' => 'Maria Psi', 'document' => '52998224725', 'whatsapp' => '11988887777']);
        $this->patient = Patients::query()->create(['user_id' => $this->user->id, 'name' => 'Ana Souza', 'document' => '11144477735', 'phone' => '11999990000']);
    }

    private function agenda(array $override = []): Agenda
    {
        return Agenda::query()->create(array_merge([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'type' => 'online',
            'scheduled_on' => '2030-10-07 12:00:00',
            'ends_at' => '2030-10-07 13:00:00',
            'status' => AgendaStatusEnum::SCHEDULED->value,
        ], $override));
    }

    private function roomOf(Agenda $agenda): ?VideoRoom
    {
        return VideoRoom::query()->where('session_id', (string) $agenda->id)->with('participants')->latest('id')->first();
    }

    private function cpfHmac(string $cpf): string
    {
        return $this->cpfHasher->hmac($cpf);
    }

    private function phoneHmac(string $phone): string
    {
        return $this->phoneHasher->hmac((string) $this->phoneHasher->normalize($phone));
    }

    private function patientWithoutCpf(string $phone = '11999990000'): void
    {
        $this->patient->forceFill(['document' => null, 'phone' => $phone])->saveQuietly();
    }

    public function testItAttachCreatesRoomAndStoresLink(): void
    {
        // Arrange
        $agenda = $this->agenda();

        // Act
        $attached = $this->service->attach($agenda->id);

        // Assert
        $videoRoom = $this->roomOf($agenda);
        $this->assertTrue($attached);
        $this->assertSame(config('psi_video.jitsi_base_url').'/'.$videoRoom->room, $agenda->fresh()->room_link);
        $this->assertSame(VideoRoomStatusEnum::ACTIVE, $videoRoom->status);
        $this->assertSame('2030-10-07 12:00:00', $videoRoom->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2030-10-07 13:00:00', $videoRoom->ends_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(
            [
                [$this->cpfHmac('52998224725'), null, 'Maria Psi', VideoRoomRoleEnum::MODERATOR],
                [$this->cpfHmac('11144477735'), null, 'Ana Souza', VideoRoomRoleEnum::GUEST],
            ],
            $videoRoom->participants->map(fn ($p) => [$p->cpf_hmac, $p->phone_hmac, $p->name, $p->role])->all(),
        );
    }

    public function testItAttachIgnoresInPersonAgenda(): void
    {
        $agenda = $this->agenda(['type' => 'in_person']);

        $this->assertFalse($this->service->attach($agenda->id));
        $this->assertNull($this->roomOf($agenda));
    }

    public function testItAttachIgnoresCanceledAgenda(): void
    {
        $agenda = $this->agenda(['status' => AgendaStatusEnum::CANCELED->value]);

        $this->assertFalse($this->service->attach($agenda->id));
        $this->assertNull($this->roomOf($agenda));
    }

    public function testItAttachKeepsExistingLink(): void
    {
        $agenda = $this->agenda(['room_link' => 'https://zoom.us/j/1']);

        $this->assertFalse($this->service->attach($agenda->id));
        $this->assertSame('https://zoom.us/j/1', $agenda->fresh()->room_link);
        $this->assertNull($this->roomOf($agenda));
    }

    public function testItAttachIgnoresMissingAgenda(): void
    {
        $this->assertFalse($this->service->attach(0));
    }

    public function testItAttachThrowExceptionWhenPsychologistHasNoValidCpf(): void
    {
        $this->user->forceFill(['document' => '11111111111'])->save();
        $agenda = $this->agenda();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('video-room.psychologist_document_required'));

        $this->service->attach($agenda->id);
    }

    public function testItAttachDoesNotRequirePsychologistWhatsapp(): void
    {
        $this->user->forceFill(['whatsapp' => null])->save();
        $agenda = $this->agenda();

        $this->assertTrue($this->service->attach($agenda->id));
    }

    public function testItAttachUsesPhoneWhenPatientHasNoCpf(): void
    {
        $this->patientWithoutCpf();
        $agenda = $this->agenda();

        $this->service->attach($agenda->id);

        $guest = $this->roomOf($agenda)->participants->firstWhere('role', VideoRoomRoleEnum::GUEST);
        $this->assertNull($guest->cpf_hmac);
        $this->assertSame($this->phoneHmac('11999990000'), $guest->phone_hmac);
    }

    public function testItAttachLeavesOutPatientWithoutCpfAndWithInvalidPhone(): void
    {
        $this->patientWithoutCpf('123');
        $agenda = $this->agenda();

        $this->service->attach($agenda->id);

        $this->assertSame([VideoRoomRoleEnum::MODERATOR], $this->roomOf($agenda)->participants->pluck('role')->all());
    }

    public function testItAttachLeavesOutPatientWithoutCpfAndWithPsychologistPhone(): void
    {
        $this->patientWithoutCpf('11988887777');
        $agenda = $this->agenda();

        $this->service->attach($agenda->id);

        $this->assertSame([VideoRoomRoleEnum::MODERATOR], $this->roomOf($agenda)->participants->pluck('role')->all());
    }

    public function testItAttachLeavesOutPatientWithPsychologistCpf(): void
    {
        $this->patient->forceFill(['document' => '52998224725'])->saveQuietly();
        $agenda = $this->agenda();

        $this->service->attach($agenda->id);

        $this->assertSame([VideoRoomRoleEnum::MODERATOR], $this->roomOf($agenda)->participants->pluck('role')->all());
    }

    public function testItAttachIgnoresPayerDocument(): void
    {
        $this->patient->forceFill(['payer_name' => 'João', 'payer_document' => '39053344705'])->saveQuietly();
        $agenda = $this->agenda();

        $this->service->attach($agenda->id);

        $this->assertNotContains($this->cpfHmac('39053344705'), $this->roomOf($agenda)->participants->pluck('cpf_hmac')->all());
    }

    public function testItAttachCancelsRoomWhenLinkWasStoredConcurrently(): void
    {
        // Arrange: a consulta é lida sem link, mas outro processo grava um antes deste
        $agenda = $this->agenda();
        $loaded = $agenda->load(['user:id,name,document,whatsapp', 'patient:id,name,document,phone']);
        $agendaModel = Mockery::mock(Agenda::class);
        $agendaModel->shouldReceive('findForVideoRoom')->once()->with($agenda->id, Mockery::type('array'))->andReturn($loaded);
        $agendaModel->shouldReceive('assignRoomLinkIfEmpty')->once()->andReturnFalse();
        $service = new AgendaVideoRoomService(agenda: $agendaModel);

        // Act
        $attached = $service->attach($agenda->id);

        // Assert
        $this->assertFalse($attached);
        $this->assertSame(VideoRoomStatusEnum::CANCELLED, $this->roomOf($agenda)->status);
    }

    public function testItRescheduleMovesRoomWindowWithSameLink(): void
    {
        $agenda = $this->agenda();
        $this->service->attach($agenda->id);
        $link = $agenda->fresh()->room_link;

        $agenda->update(['scheduled_on' => '2030-10-08 15:00:00', 'ends_at' => '2030-10-08 16:00:00']);

        $videoRoom = $this->roomOf($agenda);
        $this->assertSame('2030-10-08 15:00:00', $videoRoom->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2030-10-08 16:00:00', $videoRoom->ends_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($link, $agenda->fresh()->room_link);
    }

    public function testItCancelClosesRoomWhenAgendaIsCanceled(): void
    {
        $agenda = $this->agenda();
        $this->service->attach($agenda->id);

        $agenda->update(['status' => AgendaStatusEnum::CANCELED->value]);

        $this->assertSame(VideoRoomStatusEnum::CANCELLED, $this->roomOf($agenda)->status);
    }

    public function testItCancelClosesRoomWhenAgendaIsDeleted(): void
    {
        $agenda = $this->agenda();
        $this->service->attach($agenda->id);

        $agenda->delete();

        $this->assertSame(VideoRoomStatusEnum::CANCELLED, $this->roomOf($agenda)->status);
    }

    public function testItRefreshParticipantsWhenPatientDocumentChanges(): void
    {
        // Arrange
        $upcoming = $this->agenda();
        $past = $this->agenda(['scheduled_on' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHour()]);
        $this->service->attach($upcoming->id);
        $this->service->attach($past->id);

        // Act
        $this->patient->update(['document' => '39053344705']);

        // Assert: só a consulta ainda por vir passa a aceitar o novo CPF
        $this->assertContains($this->cpfHmac('39053344705'), $this->roomOf($upcoming)->participants->pluck('cpf_hmac')->all());
        $this->assertNotContains($this->cpfHmac('11144477735'), $this->roomOf($upcoming)->participants->pluck('cpf_hmac')->all());
        $this->assertContains($this->cpfHmac('11144477735'), $this->roomOf($past)->participants->pluck('cpf_hmac')->all());
    }

    public function testItRefreshParticipantsWhenPatientWithoutCpfChangesPhone(): void
    {
        $this->patientWithoutCpf();
        $agenda = $this->agenda();
        $this->service->attach($agenda->id);

        $this->patient->update(['phone' => '11977776666']);

        $this->assertSame([null, $this->phoneHmac('11977776666')], $this->roomOf($agenda)->participants->pluck('phone_hmac')->all());
    }

    public function testItReplacesPhoneWithCpfWhenPatientDocumentIsFilled(): void
    {
        $this->patientWithoutCpf();
        $agenda = $this->agenda();
        $this->service->attach($agenda->id);

        $this->patient->update(['document' => '11144477735']);

        $guest = $this->roomOf($agenda)->participants->firstWhere('role', VideoRoomRoleEnum::GUEST);
        $this->assertSame($this->cpfHmac('11144477735'), $guest->cpf_hmac);
        $this->assertNull($guest->phone_hmac);
    }

    public function testItRefreshParticipantsWhenPsychologistDocumentIsFilled(): void
    {
        $agenda = $this->agenda();
        $this->service->attach($agenda->id);
        $this->user->forceFill(['document' => null])->saveQuietly();

        (new ProfileService)->update($this->user, ['document' => '39053344705']);

        $moderator = $this->roomOf($agenda)->participants->firstWhere('role', VideoRoomRoleEnum::MODERATOR);
        $this->assertSame($this->cpfHmac('39053344705'), $moderator->cpf_hmac);
    }

    public function testItRefreshParticipantsIgnoresCanceledRooms(): void
    {
        $agenda = $this->agenda();
        $this->service->attach($agenda->id);
        $agenda->update(['status' => AgendaStatusEnum::CANCELED->value]);

        $this->patient->update(['document' => '39053344705']);

        $this->assertNotContains($this->cpfHmac('39053344705'), $this->roomOf($agenda)->participants->pluck('cpf_hmac')->all());
    }
}
