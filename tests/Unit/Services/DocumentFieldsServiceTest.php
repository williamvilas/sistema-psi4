<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DocumentsEnum;
use App\Models\Agenda;
use App\Models\Documents;
use App\Models\User;
use App\Models\UserDocumentDefaults;
use App\Services\DocumentFieldsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class DocumentFieldsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $documents;
    private $defaults;
    private $agenda;
    private DocumentFieldsService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Auth::login($this->user);

        $this->documents = Mockery::mock(Documents::class);
        $this->defaults = Mockery::mock(UserDocumentDefaults::class);
        $this->agenda = Mockery::mock(Agenda::class);

        $this->service = new DocumentFieldsService($this->documents, $this->defaults, $this->agenda);
    }

    private function mockDocument(DocumentsEnum $type): void
    {
        $doc = (new Documents)->forceFill(['id' => 1, 'type' => $type]);

        $this->documents->shouldReceive('findById')
            ->with(1, $this->user->id, ['id', 'type'])
            ->andReturn($doc);
    }

    public function testItReturnsAgendaFlagWithoutExtraFieldsForAttendanceStatement(): void
    {
        $this->mockDocument(DocumentsEnum::ATTENDANCE_STATEMENT);
        $this->defaults->shouldReceive('valuesByUserAndType')->andReturn([]);

        $result = $this->service->fields(1, $this->user->id);

        $this->assertTrue($result['has_agenda']);
        $this->assertSame([], $result['fields']);
    }

    public function testItReturnsTwoTextFieldsForPsychologicalCertificate(): void
    {
        $this->mockDocument(DocumentsEnum::PSYCHOLOGICAL_CERTIFICATE);
        $this->defaults->shouldReceive('valuesByUserAndType')->andReturn([]);

        $result = $this->service->fields(1, $this->user->id);

        $this->assertFalse($result['has_agenda']);
        $this->assertSame(['text_1', 'text_2'], array_column($result['fields'], 'key'));
        $this->assertSame('', $result['fields'][0]['value']);
    }

    public function testItReturnsSystemDefaultTextForPsychologistStatement(): void
    {
        $this->mockDocument(DocumentsEnum::PSYCHOLOGIST_STATEMENT);
        $this->defaults->shouldReceive('valuesByUserAndType')->andReturn([]);

        $result = $this->service->fields(1, $this->user->id);
        $fields = array_column($result['fields'], null, 'key');

        $this->assertSame(['date', 'frequency', 'text_1'], array_keys($fields));
        $this->assertSame('Comprovação da realização de acompanhamento psicológico.', $fields['text_1']['value']);
        $this->assertCount(6, $fields['frequency']['options']);
    }

    public function testItReturnsUserDefaultOverSystemDefault(): void
    {
        $this->mockDocument(DocumentsEnum::PSYCHOLOGIST_STATEMENT);
        $this->defaults->shouldReceive('valuesByUserAndType')->andReturn(['text_1' => 'Meu texto']);

        $result = $this->service->fields(1, $this->user->id);

        $this->assertSame('Meu texto', array_column($result['fields'], null, 'key')['text_1']['value']);
    }

    public function testItKeepsBlankUserDefaultInsteadOfSystemDefault(): void
    {
        $this->mockDocument(DocumentsEnum::PSYCHOLOGIST_STATEMENT);
        $this->defaults->shouldReceive('valuesByUserAndType')->andReturn(['text_1' => null]);

        $result = $this->service->fields(1, $this->user->id);

        $this->assertSame('', array_column($result['fields'], null, 'key')['text_1']['value']);
    }

    public function testItReturnsSixTextFieldsForPsychologicalReport(): void
    {
        $this->mockDocument(DocumentsEnum::PSYCHOLOGICAL_REPORT);
        $this->defaults->shouldReceive('valuesByUserAndType')->andReturn([]);

        $result = $this->service->fields(1, $this->user->id);

        $fields = array_column($result['fields'], null, 'key');

        $this->assertSame(['text_6', 'text_5', 'text_1', 'text_2', 'text_3', 'text_4'], array_keys($fields));
        $this->assertNotSame('', $fields['text_1']['value']);
        $this->assertSame('', $fields['text_6']['value']);
        $this->assertSame('Solicitante', $fields['text_6']['label']);
    }

    public function testItThrowsExceptionWhenDocumentNotFound(): void
    {
        $this->documents->shouldReceive('findById')->andReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('documents.not_found'));

        $this->service->fields(1, $this->user->id);
    }

    public function testItReturnsFirstSessionDateInUserTimezone(): void
    {
        $first = (new Agenda)->forceFill(['scheduled_on' => Carbon::parse('2026-03-10 01:30:00', 'UTC')]);

        $this->agenda->shouldReceive('firstCompletedByPatient')->with($this->user->id, 7)->andReturn($first);

        $date = $this->service->firstSessionDate(7, $this->user->id, 'America/Sao_Paulo');

        $this->assertSame('09/03/2026', $date);
    }

    public function testItReturnsNullFirstSessionDateWhenPatientHasNoCompletedSession(): void
    {
        $this->agenda->shouldReceive('firstCompletedByPatient')->andReturn(null);

        $this->assertNull($this->service->firstSessionDate(7, $this->user->id, 'America/Sao_Paulo'));
    }

    public function testItSaveDefaultSuccessfully(): void
    {
        $this->mockDocument(DocumentsEnum::PSYCHOLOGICAL_REPORT);
        $this->defaults->shouldReceive('saveValue')
            ->once()
            ->with($this->user->id, 'psychological_report', 'text_2', 'Texto')
            ->andReturn(new UserDocumentDefaults);

        $this->service->saveDefault(1, $this->user->id, 'text_2', 'Texto');

        $this->addToAssertionCount(1);
    }

    public function testItThrowsExceptionWhenSavingDefaultForNonTextField(): void
    {
        $this->mockDocument(DocumentsEnum::PSYCHOLOGIST_STATEMENT);
        $this->defaults->shouldNotReceive('saveValue');

        $this->expectException(\DomainException::class);

        $this->service->saveDefault(1, $this->user->id, 'frequency', 'daily');
    }

    public function testItResetDefaultReturnsSystemDefault(): void
    {
        $this->mockDocument(DocumentsEnum::PSYCHOLOGIST_STATEMENT);
        $this->defaults->shouldReceive('deleteValue')
            ->once()
            ->with($this->user->id, 'psychologist_statement', 'text_1');

        $value = $this->service->resetDefault(1, $this->user->id, 'text_1');

        $this->assertSame('Comprovação da realização de acompanhamento psicológico.', $value);
    }

    public function testItResetDefaultReturnsEmptyWhenNoSystemDefault(): void
    {
        $this->mockDocument(DocumentsEnum::PSYCHOLOGICAL_CERTIFICATE);
        $this->defaults->shouldReceive('deleteValue')->once();

        $this->assertSame('', $this->service->resetDefault(1, $this->user->id, 'text_1'));
    }

    public function testItThrowsExceptionWhenResettingDefaultForUnknownField(): void
    {
        $this->mockDocument(DocumentsEnum::PSYCHOLOGICAL_CERTIFICATE);
        $this->defaults->shouldNotReceive('deleteValue');

        $this->expectException(\DomainException::class);

        $this->service->resetDefault(1, $this->user->id, 'text_5');
    }
}
