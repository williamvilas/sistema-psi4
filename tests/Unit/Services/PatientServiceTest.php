<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Helpers\DateTimeHelper;
use App\Helpers\NumberHelper;
use App\Helpers\UserHelper;
use App\Models\Patients;
use App\Models\User;
use App\Services\PatientService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PatientServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $patients;
    private $userHelper;
    private $numberHelper;
    private $dateTimeHelper;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patients = Mockery::mock(Patients::class);
        $this->userHelper = Mockery::mock(UserHelper::class);
        $this->numberHelper = Mockery::mock(NumberHelper::class);
        $this->dateTimeHelper = Mockery::mock(DateTimeHelper::class);

        $this->service = new PatientService(
            $this->patients,
            $this->userHelper,
            $this->numberHelper,
            $this->dateTimeHelper
        );

        $this->user = User::factory()->create();
    }

    public function testItStoreSuccessfully(): void
    {
        // Arrange
        config(['plans.basic.total_patients' => 10]);

        $data = [
            'name' => 'John Doe',
            'document' => '123.456.789-00',
            'phone' => '(11) 99999-9999',
            'birth_date' => '2000-01-01',
        ];

        $this->numberHelper
            ->shouldReceive('onlyNumbers')
            ->with($data['document'])
            ->andReturn('12345678900');

        $this->patients
            ->shouldReceive('getPatient')
            ->andReturn(null);

        $this->numberHelper
            ->shouldReceive('onlyNumbers')
            ->with($data['phone'])
            ->andReturn('11999999999');

        $this->patients
            ->shouldReceive('getPatientByPhone')
            ->andReturn(null);

        $this->dateTimeHelper
            ->shouldReceive('convertToDatabase')
            ->twice()
            ->andReturn('2000-01-01');

        $this->patients
            ->shouldReceive('create')
            ->andReturn(new Patients());

        // Act
        $result = $this->service->store($data, $this->user);

        // Assert
        $this->assertInstanceOf(Patients::class, $result);
    }

    public function testItUpdateSuccessfully(): void
    {
        // Arrange
        $data = [
            'name' => 'Updated',
            'document' => '123',
            'phone' => '999',
        ];

        $this->numberHelper
            ->shouldReceive('onlyNumbers')
            ->with('123')
            ->andReturn('123');

        $this->patients
            ->shouldReceive('getPatient')
            ->andReturn(null);

        $this->numberHelper
            ->shouldReceive('onlyNumbers')
            ->with('999')
            ->andReturn('999');

        $this->patients
            ->shouldReceive('getPatientByPhone')
            ->andReturn(null);

        $this->patients
            ->shouldReceive('updateData')
            ->andReturn(true);

        // Act
        $this->service->update(1, $data, $this->user->id);

        // Assert
        $this->assertTrue(true);
    }

    public function testItThrowExceptionWhenUpdateFails(): void
    {
        // Arrange
        $data = [
            'name' => 'Updated',
            'phone' => '999',
        ];

        $this->numberHelper
            ->shouldReceive('onlyNumbers')
            ->andReturn('999');

        $this->patients
            ->shouldReceive('getPatientByPhone')
            ->andReturn(null);

        $this->patients
            ->shouldReceive('updateData')
            ->andReturn(false);

        // Expect
        $this->expectException(\DomainException::class);

        // Act
        $this->service->update(1, $data, $this->user->id);
    }

    /*public function testItPaginateRecordsWithoutFilters(): void
    {
        // Arrange
        $mockQuery = Mockery::mock();
        $paginator = Mockery::mock();

        $this->patients
            ->shouldReceive('newQuery')
            ->andReturn($mockQuery);

        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('when')->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('paginate')->andReturn($paginator);

        // Act
        $result = $this->service->paginate($this->user->id);

        // Assert
        $this->assertEquals($paginator, $result);
    }*/

    public function testItHandleEmptyDelete(): void
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->patients
            ->shouldReceive('deleteInBatch')
            ->andReturn(true);

        // Act
        $this->service->delete([], $this->user->id);

        // Assert
        $this->assertTrue(true);
    }

    public function testItThrowExceptionWhenDeleteFails(): void
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->andThrow(new \Exception('fail'));

        // Expect
        $this->expectException(\DomainException::class);

        // Act
        $this->service->delete(['invalid'], $this->user->id);
    }

    public function testItReturnDetails(): void
    {
        // Arrange
        $patient = new Patients();

        $this->patients
            ->shouldReceive('findById')
            ->andReturn($patient);

        $this->patients
            ->shouldReceive('countSamePhone')
            ->once()
            ->andReturn(1);

        // Act
        $result = $this->service->details(1, $this->user->id);

        // Assert
        $this->assertSame($patient, $result);
        $this->assertSame(1, $result->duplicate_phone_count);
    }

    public function testItGetPatientByPhone(): void
    {
        // Arrange
        $patient = new Patients();

        $this->numberHelper
            ->shouldReceive('onlyNumbers')
            ->andReturn('999');

        $this->patients
            ->shouldReceive('getPatientByPhone')
            ->andReturn($patient);

        // Act
        $result = $this->service->getPatientByPhone('999', $this->user->id);

        // Assert
        $this->assertEquals($patient, $result);
    }

    /*public function testItAutocomplete(): void
    {
        // Arrange
        $patient = new Patients();
        $patient->id = 1;
        $patient->name = 'John';
        $patient->phone = '999';

        $this->patients
            ->shouldReceive('autocomplete')
            ->andReturn([$patient]);

        // Act
        $result = $this->service->autocomplete('jo', $this->user->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('John', $result[0]['name']);
    }*/
}
