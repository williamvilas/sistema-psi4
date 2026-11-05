<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\MedicalRecords;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MedicalRecordsTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Patients $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->patient = Patients::create([
            'user_id' => $this->user->id,
            'name' => 'Paciente Teste',
            'phone' => '11999999999',
        ]);
    }

    private function createRecord(string $title, string $date): MedicalRecords
    {
        return MedicalRecords::create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'title' => $title,
            'notes_path' => 'medical-records/'.uniqid().'.html',
            'date_of_consultation' => $date,
        ]);
    }

    public function testItPaginateByUserOrdersByConsultationDateDesc(): void
    {
        // Arrange: a consulta mais antiga é cadastrada por último (id maior)
        $this->createRecord('Sessão recente', '2026-10-15');
        $this->createRecord('Sessão antiga', '2026-10-10');

        // Act
        $records = (new MedicalRecords())->paginateByUser(userId: $this->user->id, limit: 10, params: []);

        // Assert
        $this->assertSame(['Sessão recente', 'Sessão antiga'], $records->pluck('title')->all());
    }

    public function testItPaginateByUserOrdersByIdDescWhenSameConsultationDate(): void
    {
        // Arrange
        $this->createRecord('Primeira do dia', '2026-10-15');
        $this->createRecord('Segunda do dia', '2026-10-15');

        // Act
        $records = (new MedicalRecords())->paginateByUser(userId: $this->user->id, limit: 10, params: []);

        // Assert
        $this->assertSame(['Segunda do dia', 'Primeira do dia'], $records->pluck('title')->all());
    }

    public function testItPaginateByUserFiltersByTitleAndPeriod(): void
    {
        // Arrange
        $this->createRecord('Sessão de hoje', '2026-10-15');
        $this->createRecord('Sessão do mês passado', '2026-09-15');
        $this->createRecord('Avaliação de hoje', '2026-10-15');

        // Act
        $records = (new MedicalRecords())->paginateByUser(userId: $this->user->id, limit: 10, params: [
            'q' => 'Sessão',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        // Assert
        $this->assertSame(['Sessão de hoje'], $records->pluck('title')->all());
    }
}
